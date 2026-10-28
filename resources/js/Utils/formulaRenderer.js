import katex from 'katex';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

// Configure marked with GFM (GitHub Flavored Markdown) and line breaks
marked.setOptions({
  gfm: true,
  breaks: true,
});

/**
 * Render text containing Markdown and LaTeX formulas to HTML.
 * Preserves KaTeX equations while fully parsing tables, lists, code, and typography,
 * while safely sanitizing HTML to prevent Stored XSS.
 */
export function renderFormulaContent(rawText) {
  if (!rawText) return '';

  let text = String(rawText);
  const mathTokens = [];

  // 1. Protect block math $$ ... $$
  text = text.replace(/\$\$([\s\S]+?)\$\$/g, (match, formula) => {
    try {
      const rendered = `<div class="katex-display-container my-4 flex justify-center overflow-x-auto py-3 px-4 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-sm shadow-inner">${katex.renderToString(formula.trim(), {
        displayMode: true,
        throwOnError: false,
      })}</div>`;
      const token = `@@@MATH_BLOCK_${mathTokens.length}@@@`;
      mathTokens.push({ token, html: rendered });
      return token;
    } catch (e) {
      return match;
    }
  });

  // 2. Protect inline math $ ... $
  text = text.replace(/(?<!\\)\$([^\$\n]+?)\$/g, (match, formula) => {
    try {
      const rendered = katex.renderToString(formula.trim(), {
        displayMode: false,
        throwOnError: false,
      });
      const token = `@@@MATH_INLINE_${mathTokens.length}@@@`;
      mathTokens.push({ token, html: rendered });
      return token;
    } catch (e) {
      return match;
    }
  });

  // 3. Parse Markdown using marked
  let html = marked.parse(text);

  // 4. Sanitize markdown HTML with DOMPurify to prevent Stored XSS
  if (typeof DOMPurify !== 'undefined' && DOMPurify.sanitize) {
    html = DOMPurify.sanitize(html, {
      ADD_ATTR: ['target', 'rel', 'class', 'style'],
    });
  }

  // 5. Restore KaTeX tokens
  mathTokens.forEach(({ token, html: mathHtml }) => {
    html = html.replace(token, () => mathHtml);
  });

  return html;
}

/**
 * Quick science formula presets for teachers
 */
export const SCIENCE_PRESETS = {
  matematika: [
    { label: 'Pecahan', symbol: 'a/b', latex: '$$\\frac{a}{b}$$' },
    { label: 'Akar Kuadrat', symbol: '√x', latex: '$$\\sqrt{x}$$' },
    { label: 'Pangkat', symbol: 'x²', latex: '$$x^2$$' },
    { label: 'Rumus ABC', symbol: 'x1,2', latex: '$$x = \\frac{-b \\pm \\sqrt{b^2 - 4ac}}{2a}$$' },
    { label: 'Plus Minus', symbol: '±', latex: '$\\pm$' },
    { label: 'Kali & Bagi', symbol: '× ÷', latex: '$\\times$ dan $\\div$' },
    { label: 'Pi & Theta', symbol: 'π θ', latex: '$\\pi$, $\\theta$' },
    { label: 'Integral', symbol: '∫', latex: '$$\\int_{a}^{b} f(x)\\,dx$$' },
    { label: 'Sigma', symbol: '∑', latex: '$$\\sum_{i=1}^{n} x_i$$' },
    { label: 'Limit', symbol: 'lim', latex: '$$\\lim_{x \\to \\infty} f(x)$$' },
  ],
  fisika: [
    { label: 'Hukum Newton II', symbol: 'F=m.a', latex: '$$\\vec{F} = m \\cdot \\vec{a}$$' },
    { label: 'Kecepatan', symbol: 'v=s/t', latex: '$$v = \\frac{s}{t}$$' },
    { label: 'Energi Kinetik', symbol: 'Ek', latex: '$$E_k = \\frac{1}{2} m v^2$$' },
    { label: 'Energi Potensial', symbol: 'Ep', latex: '$$E_p = m \\cdot g \\cdot h$$' },
    { label: 'Hukum Ohm', symbol: 'V=I.R', latex: '$$V = I \\cdot R$$' },
    { label: 'Panjang Gelombang', symbol: 'λ', latex: '$$\\lambda = \\frac{v}{f}$$' },
    { label: 'Delta Suhu', symbol: 'ΔT', latex: '$$\\Delta T = T_2 - T_1$$' },
    { label: 'Usaha & Kalor', symbol: 'Q=m.c.ΔT', latex: '$$Q = m \\cdot c \\cdot \\Delta T$$' },
  ],
  kimia: [
    { label: 'Air (H₂O)', symbol: 'H₂O', latex: '$$H_2O$$' },
    { label: 'Karbon Dioksida', symbol: 'CO₂', latex: '$$CO_2$$' },
    { label: 'Asam Klorida', symbol: 'HCl', latex: '$$HCl$$' },
    { label: 'Asam Sulfat', symbol: 'H₂SO₄', latex: '$$H_2SO_4$$' },
    { label: 'Glukosa', symbol: 'C₆H₁₂O₆', latex: '$$C_6H_{12}O_6$$' },
    { label: 'Panah Reaksi', symbol: '→', latex: '$$\\rightarrow$$' },
    { label: 'Reaksi Bolak-balik', symbol: '⇌', latex: '$$\\rightleftharpoons$$' },
    { label: 'Gas Oksigen', symbol: 'O₂', latex: '$$O_2$$' },
    { label: 'Ion Positif/Negatif', symbol: 'Na⁺ Cl⁻', latex: '$$Na^+ + Cl^- \\rightarrow NaCl$$' },
  ]
};
