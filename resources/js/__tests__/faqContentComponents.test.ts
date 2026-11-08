import { enableAutoUnmount, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import MultilanguageEditor from '../components/admin/MultilanguageEditor.vue';
import FaqAccordion from '../components/faq/FaqAccordion.vue';

enableAutoUnmount(afterEach);

/** Mount an isolated FAQ list with synthetic authored answer content. */
const mountFaq = (answer = 'A plain answer.') => mount(FaqAccordion, {
  props: { items: [{ id: 'synthetic-faq', question: 'A synthetic question?', answer }] },
});

/** Mount the shared editor with the supplied synthetic locale payload. */
const mountEditor = (modelValue: Record<string, string>) => mount(MultilanguageEditor, {
  props: { modelValue, label: 'Question' },
});

/** Select canonical Czech and expose its input without changing stored text. */
async function czechInput(modelValue: Record<string, string>) {
  const wrapper = mountEditor(modelValue);
  await wrapper.findAll('nav button').find(button => button.text() === 'Čeština')!.trigger('click');
  return { wrapper, input: wrapper.findAll('input')[3] };
}

/** Build balanced alternating emphasis with named inner/outer tails for scope assertions. */
const balancedEmphasis = (layers: number) => {
  const delimiters = Array.from({ length: layers }, (_, index) => index % 2 ? '*' : '**');
  const openings = delimiters.map((delimiter, index) => `${delimiter}open-${index} `);
  const closings = delimiters.map((delimiter, index) => ` close-${index}${delimiter}`).reverse();
  const plain = Array.from({ length: layers }, (_, index) => `open-${index} `).join('')
    + 'center' + Array.from({ length: layers }, (_, index) => ` close-${layers - index - 1}`).join('');
  return { answer: openings.join('') + '[center](/documentation/guide)' + closings.join(''), plain };
};

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
    ['**[guide](/documentation/guide)**', '.prose strong a'],
    ['*[guide](/documentation/guide)*', '.prose em a'],
    ['**Keep *[guide](/documentation/guide)* safe**', '.prose strong em a'],
    ['*Keep **[guide](/documentation/guide)** safe*', '.prose em strong a'],
    ['***[guide](/documentation/guide)***', '.prose strong em a'],
  ])('keeps nested documentation links clickable in %s', (answer, selector) => {
    const wrapper = mountFaq(answer);
    expect(wrapper.get(selector).attributes('href')).toBe('/documentation/guide');
    expect(wrapper.get(selector).text()).toBe('guide');
  });

  it('consumes the complete validator-supported label including opening brackets', () => {
    const wrapper = mountFaq('[outer [inner](/documentation/guide)');
    expect(wrapper.get('.prose a').text()).toBe('outer [inner');
    expect(wrapper.get('.prose').text()).toBe('outer [inner');
    expect(wrapper.get('.prose a').attributes('href')).toBe('/documentation/guide');
  });

  it.each(['\r', '\n', '\r\n'])('rejects label line breaks consistently for %j', lineBreak => {
    const wrapper = mountFaq(`[first${lineBreak}second](/documentation/guide)`);
    expect(wrapper.find('.prose a').exists()).toBe(false);
  });

  it('sanitizes the complete nested-bracket label rather than rendering a literal prefix', () => {
    const wrapper = mountFaq('[outer [<strong onclick="alert(1)">inner</strong>](/documentation/guide)');
    expect(wrapper.get('.prose a').text()).toBe('outer [inner');
    expect(wrapper.get('.prose a strong').text()).toBe('inner');
    expect(wrapper.get('.prose').text()).toBe('outer [inner');
    expect(wrapper.find('.prose [onclick]').exists()).toBe(false);
  });

  it('keeps links clickable inside capped emphasis and after the capped span', () => {
    const delimiters = Array.from({ length: 12 }, (_, index) => index % 2 ? '*' : '**');
    const opening = delimiters.map(delimiter => `${delimiter}layer `).join('');
    const closing = delimiters.slice().reverse().map(delimiter => ` layer${delimiter}`).join('');
    const wrapper = mountFaq(`${opening}[inside](/documentation/guide)${closing} [after](/documentation/backup-and-recovery)`);
    expect(wrapper.findAll('.prose a').map(link => [link.text(), link.attributes('href')])).toEqual([
      ['inside', '/documentation/guide'], ['after', '/documentation/backup-and-recovery'],
    ]);
  });

  it.each([9, 10, 12, 64])('matches balanced %i-layer delimiters without leaking markers or outer-tail scope', layers => {
    const { answer, plain } = balancedEmphasis(layers);
    const wrapper = mountFaq(`${answer} outside [tail](/documentation/backup-and-recovery)`);
    expect(wrapper.get('.prose').text()).toBe(`${plain} outside tail`);
    expect(wrapper.get('.prose').text()).not.toContain('*');
    expect(wrapper.findAll('.prose strong, .prose em')).toHaveLength(8);
    const outer = wrapper.get('.prose > p > strong');
    expect(outer.text()).toBe(plain);
    expect(outer.text()).not.toContain('outside');
    expect(wrapper.get('.prose > p > a').text()).toBe('tail');
    expect(wrapper.findAll('.prose a').map(link => link.attributes('href'))).toEqual([
      '/documentation/guide', '/documentation/backup-and-recovery',
    ]);
  });

  it.each([0, 7, 8, 9, 64])('consumes balanced label emphasis inside %i outer layers even when formatting is capped', layers => {
    const { answer, plain } = balancedEmphasis(layers);
    const wrapper = mountFaq(answer.replace('[center]', '[**center**]'));
    expect(wrapper.get('.prose').text()).toBe(plain);
    expect(wrapper.get('.prose a').text()).toBe('center');
    expect(wrapper.get('.prose').text()).not.toContain('*');
    expect(wrapper.findAll('.prose strong, .prose em').length).toBeLessThanOrEqual(8);
  });

  it('matches ten thousand logical layers with bounded rendered depth and no recursive unwinding', () => {
    const { answer, plain } = balancedEmphasis(10000);
    const wrapper = mountFaq(`${answer} outside [tail](/documentation/backup-and-recovery)`);
    expect(wrapper.get('.prose').text()).toBe(`${plain} outside tail`);
    expect(wrapper.findAll('.prose strong, .prose em')).toHaveLength(8);
    expect(wrapper.get('.prose > p > strong').text()).toBe(plain);
    expect(wrapper.get('.prose > p > a').text()).toBe('tail');
  });

  it.each([
    ['Plain *literal', 'Plain *literal'],
    ['Unmatched **literal', 'Unmatched **literal'],
    ['***literal**', '***literal**'],
    ['**outer *inner*', '**outer inner'],
  ])('preserves unmatched markers in %s', (answer, expected) => {
    const wrapper = mountFaq(answer);
    expect(wrapper.get('.prose').text()).toBe(expected);
  });

  it('retains deep unmatched openers literally while keeping documentation links clickable', () => {
    const opening = Array.from({ length: 64 }, (_, index) => index % 2 ? '*inner ' : '**outer ').join('');
    const wrapper = mountFaq(`${opening}[center](/documentation/guide)`);
    expect(wrapper.get('.prose').text()).toBe(opening + 'center');
    expect(wrapper.find('.prose strong, .prose em').exists()).toBe(false);
    expect(wrapper.get('.prose a').attributes('href')).toBe('/documentation/guide');
  });

  it('preserves triple runs and sanitizes malicious markup inside deep balanced label emphasis', () => {
    const { answer, plain } = balancedEmphasis(64);
    const wrapper = mountFaq('***before*** ' + answer.replace('[center]', '[***center<img src=x onerror="alert(1)">***]') + ' ***after***');
    expect(wrapper.get('.prose').text()).toBe(`before ${plain} after`);
    expect(wrapper.get('.prose a').text()).toBe('center');
    expect(wrapper.find('.prose img, .prose [onerror]').exists()).toBe(false);
    expect(wrapper.findAll('.prose > p > strong > em').map(node => node.text())).toContain('before');
    expect(wrapper.findAll('.prose > p > strong > em').map(node => node.text())).toContain('after');
  });

  it('preserves prior safe HTML paragraphs, breaks and emphasis without attributes', () => {
    const wrapper = mountFaq('<p class="legacy" onclick="alert(1)">First <strong title="old">bold</strong><br>and <em style="color:red">italic</em>.</p><p>Second paragraph.</p>');
    expect(wrapper.get('.prose strong').text()).toBe('bold');
    expect(wrapper.get('.prose em').text()).toBe('italic');
    expect(wrapper.findAll('.prose br')).toHaveLength(1);
    expect(wrapper.findAll('.prose p').some(node => node.text() === 'Second paragraph.')).toBe(true);
    expect(wrapper.find('.prose [onclick], .prose [class], .prose [title], .prose [style]').exists()).toBe(false);
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

  it('removes unsafe raw HTML and hostile link label elements', () => {
    const wrapper = mountFaq('<img src=x onerror="alert(1)"> <script>alert(1)</script> [guide<svg onload="alert(2)"></svg>](/documentation/guide)');
    expect(wrapper.find('.prose img, .prose script, .prose svg, .prose [onload], .prose [onerror]').exists()).toBe(false);
    expect(wrapper.get('.prose a').attributes('href')).toBe('/documentation/guide');
    expect(wrapper.get('.prose').text()).not.toContain('alert(1)');
  });

  it('combines legacy safe HTML and nested Markdown without accepting attributes', () => {
    const wrapper = mountFaq('<p data-owner="private" aria-label="injected">Use <strong>[guide](/documentation/guide)</strong> or **<em>[recovery](/documentation/backup-and-recovery)</em>**.</p>');
    expect(wrapper.findAll('.prose strong a').map(link => link.attributes('href'))).toEqual([
      '/documentation/guide', '/documentation/backup-and-recovery',
    ]);
    expect(wrapper.get('.prose strong em a').text()).toBe('recovery');
    expect(wrapper.find('.prose [data-owner], .prose [aria-label]').exists()).toBe(false);
  });

  it.each([
    '<p><a href="javascript:alert(1)">unsafe</a> <a href="/documentation/guide" onclick="alert(2)">legacy anchor</a></p>',
    '<p><strong onclick="&#97;lert(1)" data-x="1" aria-label="hidden">**[guide](/documentation/guide)**</strong></p>',
    '<svg><foreignObject><p onmouseover="alert(1)">Bad</p></foreignObject></svg> **[guide](/documentation/guide)**',
    '<math><mtext><table><mglyph><style><!--</style><img title="--><img src=x onerror=alert(1)>">',
    '<p><em>Broken **[guide](/documentation/guide)</p></strong><script>alert(1)</script>',
    '[label &quot; onclick=&quot;alert(1)](/documentation/guide) <em onfocus="alert(1)">safe</em>',
    '&lt;img src=x onerror=alert(1)&gt; **[guide](/documentation/guide)**',
  ])('sanitizes mixed or malformed markup %s', answer => {
    const wrapper = mountFaq(answer);
    const prose = wrapper.get('.prose');
    const allowed = ['P', 'BR', 'STRONG', 'EM', 'A'];
    for (const node of prose.element.querySelectorAll('*')) {
      expect(allowed).toContain(node.tagName);
      for (const attribute of Array.from(node.attributes)) {
        expect(node.tagName).toBe('A');
        expect(attribute.name).toBe('href');
        expect(attribute.value).toMatch(/^\/documentation\/[a-z0-9]+(?:-[a-z0-9]+)*$/);
      }
    }
  });

  it.each(['**', '*', '**Keep *', '*Keep **', '**<em>'])('keeps forbidden nested Markdown destinations inert inside %s', prefix => {
    const wrapper = mountFaq(`${prefix}[unsafe](javascript:alert(1)) and [unsafe](//example.com/guide)${prefix}`);
    expect(wrapper.find('.prose a, .prose [onclick]').exists()).toBe(false);
  });

  it('bounds deeply nested or unmatched emphasis while keeping its text and sanitization', () => {
    const opening = Array.from({ length: 100 }, (_, index) => index % 2 ? '*inner ' : '**outer ').join('');
    const wrapper = mountFaq(`${opening}[guide](/documentation/guide)<img src=x onerror="alert(1)">`);
    expect(wrapper.get('.prose').text()).toContain('guide');
    expect(wrapper.find('.prose img, .prose [onerror]').exists()).toBe(false);
  });

  it('retains long unmatched bracket text without interpreting nested link labels', () => {
    const answer = '['.repeat(10000) + 'ordinary text';
    const wrapper = mountFaq(answer);
    expect(wrapper.get('.prose').text()).toBe(answer);
    expect(wrapper.find('.prose a').exists()).toBe(false);
  });

  it.each([
    '['.repeat(10000) + '](javascript:bad)',
    '['.repeat(10000) + '\n' + 'x](/documentation/guide)',
  ])('retains long failed label runs and still recognizes a later safe link', prefix => {
    const wrapper = mountFaq(`${prefix} **visible** [later](/documentation/guide)`);
    expect(wrapper.findAll('.prose a').map(link => [link.text(), link.attributes('href')])).toEqual([
      ['later', '/documentation/guide'],
    ]);
    expect(wrapper.get('.prose strong').text()).toBe('visible');
  });

  it('preserves complete sanitized link labels at the emphasis recursion limit', () => {
    const delimiters = Array.from({ length: 12 }, (_, index) => index % 2 ? '*' : '**');
    const opening = delimiters.map(delimiter => `${delimiter}layer `).join('');
    const closing = delimiters.slice().reverse().map(delimiter => ` layer${delimiter}`).join('');
    const wrapper = mountFaq(`${opening}[outer [<em onclick="alert(1)">inner</em>](/documentation/guide)${closing}`);
    expect(wrapper.get('.prose a').text()).toBe('outer [inner');
    expect(wrapper.get('.prose a').attributes('href')).toBe('/documentation/guide');
    expect(wrapper.find('.prose [onclick]').exists()).toBe(false);
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
