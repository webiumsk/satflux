import { afterEach, expect, it, vi } from 'vitest';
import api, { invoicingApi } from '@/services/api';
afterEach(() => vi.restoreAllMocks());
it('PR429 forwards the mapped expense preview AbortSignal and timeout to Axios', async () => {
  const post=vi.spyOn(api,'post').mockResolvedValue({data:{data:{duplicate_warnings:[]}}});
  const controller=new AbortController(); const form=new FormData(); form.append('mapping','{"total":1}');
  const result=await invoicingApi.expenses.import.preview('synthetic-company',form,{signal:controller.signal,timeout:10000});
  expect(result).toEqual({duplicate_warnings:[]});
  expect(post).toHaveBeenCalledExactlyOnceWith('/invoicing/companies/synthetic-company/expenses/import/excel/preview',form,{signal:controller.signal,timeout:10000});
  const options=post.mock.calls[0]?.[2]; controller.abort(); expect(options?.signal?.aborted).toBe(true);
});
it('PR429 retains the existing two-argument preview API for callers without cancellation options',async()=>{
  const post=vi.spyOn(api,'post').mockResolvedValue({data:{data:{headers:['Synthetic']}}});
  const form=new FormData(); expect(await invoicingApi.expenses.import.preview('synthetic-company',form)).toEqual({headers:['Synthetic']});
  expect(post.mock.calls[0]?.[2]).toEqual({});
});
