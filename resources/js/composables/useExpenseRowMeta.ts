export type ExpenseListRow = {
  id: string;
  internal_number: string;
  external_number: string | null;
  variable_symbol?: string | null;
  title: string | null;
  issue_date: string;
  delivery_date?: string | null;
  due_date?: string | null;
  paid_at?: string | null;
  total: string;
  currency: string;
  status: string;
  internal_note?: string | null;
  is_overdue?: boolean;
  has_attachment?: boolean;
};

export function parseExpenseRowMeta(row: ExpenseListRow) {
  const note = row.internal_note ?? '';
  let category = '';
  let supplier = '';

  const categoryMatch = note.match(/Category:\s*([^\n]+)/i);
  const supplierMatch = note.match(/Supplier:\s*([^\n]+)/i);

  if (categoryMatch) {
    category = categoryMatch[1].trim();
  }
  if (supplierMatch) {
    supplier = supplierMatch[1].trim();
  }

  const title = (row.title || '').trim();
  if (title.includes(' - ')) {
    const [left, right] = title.split(' - ', 2);
    if (!supplier) {
      supplier = left.trim();
    }
    if (!category) {
      category = right.trim();
    }
  } else if (!supplier && title) {
    supplier = title;
  }

  return { category, supplier };
}

export function expenseStatusCornerClass(row: ExpenseListRow): string {
  if (row.status === 'paid') return 'status-corner--paid';
  if (row.status === 'cancelled') return 'status-corner--cancelled';
  if (row.is_overdue) return 'status-corner--overdue';
  return 'status-corner--waiting';
}

export function expenseOverdueDays(dueDate: string | null | undefined): number | null {
  if (!dueDate) return null;
  const due = new Date(`${dueDate.slice(0, 10)}T12:00:00`);
  const today = new Date();
  today.setHours(12, 0, 0, 0);
  const diff = Math.floor((today.getTime() - due.getTime()) / 86400000);

  return diff > 0 ? diff : null;
}
