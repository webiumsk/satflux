import { enableAutoUnmount, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import MultilanguageEditor from '../components/admin/MultilanguageEditor.vue';
import FaqAccordion from '../components/faq/FaqAccordion.vue';

enableAutoUnmount(afterEach);

const mountFaq = (answer = 'A plain answer.') => mount(FaqAccordion, {
  props: { items: [{ id: 'synthetic-faq', question: 'A synthetic question?', answer }] },
});

const mountEditor = (modelValue: Record<string, string>) => mount(MultilanguageEditor, {
  props: { modelValue, label: 'Question' },
});

async function czechInput(modelValue: Record<string, string>) {
  const wrapper = mountEditor(modelValue);
  await wrapper.findAll('nav button').find(button => button.text() === 'Čeština')!.trigger('click');
  return { wrapper, input: wrapper.findAll('input')[3] };
}

describe('FAQ localized content editor', () => {
  it('offers only the five canonical application locales', () => {
    const wrapper = mountEditor({ en: 'English' });
    expect(wrapper.findAll('nav button').map(button => button.text())).toEqual([
      'English', 'Slovenčina', 'Español', 'Čeština', 'Deutsch',
    ]);
  });

  it.each([undefined, '', '   '])('displays legacy Czech when cs is %s without mutating data', async cs => {
    const data = { en: 'English', cz: 'Starší čeština', ...(cs === undefined ? {} : { cs }) };
    const { wrapper, input } = await czechInput(data);
    expect((input.element as HTMLInputElement).value).toBe('Starší čeština');
    expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    expect(data.cz).toBe('Starší čeština');
  });

  it('prefers canonical Czech and retains conflicting legacy and extra language text on edits', async () => {
    const data = { en: 'English', cs: 'Současná čeština', cz: 'Starší čeština', fr: 'Français' };
    const { wrapper, input } = await czechInput(data);
    expect((input.element as HTMLInputElement).value).toBe('Současná čeština');
    await input.setValue('Upravená čeština');
    expect(wrapper.emitted('update:modelValue')![0][0]).toEqual({ ...data, cs: 'Upravená čeština' });
    expect(data.cs).toBe('Současná čeština');
  });

  it('writes legacy Czech edits to cs while retaining the original cz value', async () => {
    const { wrapper, input } = await czechInput({ cz: 'Původní text', pl: 'Polski' });
    await input.setValue('Nový text');
    expect(wrapper.emitted('update:modelValue')![0][0]).toEqual({
      cz: 'Původní text', pl: 'Polski', cs: 'Nový text',
    });
  });

  it('retains legacy and extra locales when another language is edited', async () => {
    const data = { en: 'English', cz: 'Čeština', fr: 'Français' };
    const wrapper = mountEditor(data);
    await wrapper.findAll('input')[0].setValue('Changed English');
    expect(wrapper.emitted('update:modelValue')![0][0]).toEqual({ ...data, en: 'Changed English' });
  });
});

describe('FAQ safe answer rendering', () => {
  it('renders local documentation links and existing emphasis and paragraphs', () => {
    const wrapper = mountFaq('Read [the guide](/documentation/backup-and-recovery).\n**Keep** your *backup*.\n\nSecond paragraph.');
    expect(wrapper.get('.prose a').attributes()).toEqual({ href: '/documentation/backup-and-recovery' });
    expect(wrapper.get('.prose a').text()).toBe('the guide');
    expect(wrapper.get('.prose strong').text()).toBe('Keep');
    expect(wrapper.get('.prose em').text()).toBe('backup');
    expect(wrapper.findAll('.prose p')).toHaveLength(2);
    expect(wrapper.findAll('.prose br')).toHaveLength(1);
  });

  it.each([
    'https://example.com/documentation/guide', '//example.com/guide', 'javascript:alert(1)',
    'data:text/html,hello', '/documentation/../account', '/documentation/%2e%2e/account',
    '/documentation/guide?redirect=https://example.com', '/documentation/guide#section',
    '/documentation/guide" onclick="alert(1)', '/documentation/guide%0aonmouseover=alert(1)',
  ])('does not create a link for a forbidden destination %s', href => {
    const wrapper = mountFaq(`[unsafe](${href})`);
    expect(wrapper.findAll('.prose a')).toHaveLength(0);
    expect(wrapper.findAll('.prose [onclick], .prose [onmouseover]')).toHaveLength(0);
  });

  it('treats raw HTML and hostile link labels as plain text', () => {
    const wrapper = mountFaq('<img src=x onerror="alert(1)"> <script>alert(1)</script> [<svg onload="alert(2)">](/documentation/guide)');
    expect(wrapper.find('.prose img, .prose script, .prose svg, .prose [onload], .prose [onerror]').exists()).toBe(false);
    expect(wrapper.get('.prose a').text()).toBe('<svg onload="alert(2)">');
    expect(wrapper.get('.prose').text()).toContain('<script>alert(1)</script>');
  });

  it('keeps ampersands and quotes readable while preventing attribute injection', () => {
    const wrapper = mountFaq('[A & "B"](/documentation/guide)');
    expect(wrapper.get('.prose a').text()).toBe('A & "B"');
    expect(wrapper.get('.prose a').attributes()).toEqual({ href: '/documentation/guide' });
  });

  it('exposes accordion control state and connected region labels', async () => {
    const wrapper = mountFaq();
    const button = wrapper.get('h3 button');
    const panel = wrapper.get('[role=region]');
    expect(button.attributes('type')).toBe('button');
    expect(button.attributes('aria-expanded')).toBe('false');
    expect(button.attributes('aria-controls')).toBe(panel.attributes('id'));
    expect(panel.attributes('aria-labelledby')).toBe(button.attributes('id'));
    expect(panel.attributes('style')).toContain('display: none');
    await button.trigger('click');
    expect(button.attributes('aria-expanded')).toBe('true');
    expect(panel.attributes('style') || '').not.toContain('display: none');
    await button.trigger('click');
    expect(button.attributes('aria-expanded')).toBe('false');
  });

  it('keeps panel IDs unique when two FAQ lists are present', () => {
    // Independent application roots may restart Vue useId; this is the real shared-page case.
    const combined = mount({ components: { FaqAccordion }, template: '<div><FaqAccordion :items="items"/><FaqAccordion :items="items"/></div>', data: () => ({ items: [{ id: 'same-question', question: 'Question?', answer: 'Answer.' }] }) });
    expect(combined.findAll('[role=region]').map(panel => panel.attributes('id'))[0])
      .not.toBe(combined.findAll('[role=region]').map(panel => panel.attributes('id'))[1]);
  });
});
