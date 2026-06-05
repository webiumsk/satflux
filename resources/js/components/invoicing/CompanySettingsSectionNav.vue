<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import {
  useInvoicingLayout,
  type InvoicingToolsNavItem,
} from '../../composables/useInvoicingLayout';

const props = defineProps<{
  companyId?: string;
}>();

const { t } = useI18n();
const { toolsNavItems, activeToolsSection, companyId: routeCompanyId } = useInvoicingLayout();

function linkTo(tool: InvoicingToolsNavItem) {
  if (tool.section === 'subscription') {
    return { name: tool.routeName };
  }
  const cid = routeCompanyId.value || props.companyId;
  if (!cid) {
    return { name: 'invoicing' };
  }
  return { name: tool.routeName, params: { companyId: cid } };
}
</script>
