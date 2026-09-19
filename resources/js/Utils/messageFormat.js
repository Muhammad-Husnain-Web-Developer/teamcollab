import { marked } from 'marked';
import DOMPurify from 'dompurify';

/**
 * Message body rendering: a deliberately small Markdown subset, then sanitised.
 *
 * Chat messages are user input rendered with v-html, so sanitising is not
 * optional. marked handles parsing; DOMPurify is the security boundary. The
 * allow-list below is what actually reaches the DOM — anything else (script,
 * iframe, event handlers, style, javascript: URLs) is stripped.
 */

marked.setOptions({
  breaks: true,   // a single newline is a line break, as users expect in chat
  gfm: true,      // fenced code, strikethrough, autolinks
});

const ALLOWED_TAGS = [
  'p', 'br', 'strong', 'em', 'del', 's', 'code', 'pre',
  'blockquote', 'ul', 'ol', 'li', 'a', 'span', 'hr',
];

const ALLOWED_ATTR = ['href', 'title', 'class', 'target', 'rel'];

// Force every link to open safely in a new tab. rel=noopener stops the opened
// page from reaching back through window.opener.
DOMPurify.addHook('afterSanitizeAttributes', (node) => {
  if (node.tagName === 'A') {
    node.setAttribute('target', '_blank');
    node.setAttribute('rel', 'noopener noreferrer nofollow');
  }
});

/**
 * Highlight @mentions after sanitising, so the markup we add is never itself
 * user input. Runs only on text nodes to avoid corrupting tags or URLs.
 */
function highlightMentions(html, mentionClass) {
  const template = document.createElement('template');
  template.innerHTML = html;

  const walker = document.createTreeWalker(template.content, NodeFilter.SHOW_TEXT);
  const textNodes = [];
  while (walker.nextNode()) textNodes.push(walker.currentNode);

  textNodes.forEach((node) => {
    // Mentions inside code blocks are literal text, not references.
    if (node.parentElement?.closest('code, pre, a')) return;
    if (!/@\w/.test(node.nodeValue)) return;

    const fragment = document.createDocumentFragment();
    let lastIndex = 0;
    const pattern = /@(\w+)/g;
    let match;

    while ((match = pattern.exec(node.nodeValue)) !== null) {
      fragment.append(node.nodeValue.slice(lastIndex, match.index));

      const span = document.createElement('span');
      span.className = mentionClass;
      span.textContent = match[0]; // textContent, never innerHTML
      fragment.append(span);

      lastIndex = match.index + match[0].length;
    }

    fragment.append(node.nodeValue.slice(lastIndex));
    node.replaceWith(fragment);
  });

  return template.innerHTML;
}

/**
 * Replace :shortcode: tokens with an <img> for known custom emoji, after
 * sanitising — same reasoning as highlightMentions: only text nodes are
 * touched, and the shortcode→url lookup is trusted server data, not user
 * input, so it's safe to build markup from it directly.
 */
function renderCustomEmoji(html, customEmoji) {
  if (!customEmoji || customEmoji.size === 0) return html;

  const template = document.createElement('template');
  template.innerHTML = html;

  const walker = document.createTreeWalker(template.content, NodeFilter.SHOW_TEXT);
  const textNodes = [];
  while (walker.nextNode()) textNodes.push(walker.currentNode);

  textNodes.forEach((node) => {
    if (node.parentElement?.closest('code, pre, a')) return;
    if (!/:[a-z0-9_]{2,32}:/i.test(node.nodeValue)) return;

    const fragment = document.createDocumentFragment();
    let lastIndex = 0;
    const pattern = /:([a-z0-9_]{2,32}):/gi;
    let match;

    while ((match = pattern.exec(node.nodeValue)) !== null) {
      const url = customEmoji.get(match[1].toLowerCase());
      if (!url) continue;

      fragment.append(node.nodeValue.slice(lastIndex, match.index));

      const img = document.createElement('img');
      img.src = url;
      img.alt = match[0];
      img.title = match[0];
      img.className = 'custom-emoji';
      fragment.append(img);

      lastIndex = match.index + match[0].length;
    }

    if (lastIndex === 0) return; // no known shortcode matched — leave the text node alone

    fragment.append(node.nodeValue.slice(lastIndex));
    node.replaceWith(fragment);
  });

  return template.innerHTML;
}

/**
 * Render a message body to sanitised HTML.
 *
 * @param {string} body
 * @param {{ mentionClass?: string, customEmoji?: Map<string, string> }} options
 * @returns {string}
 */
export function renderMessageBody(body, { mentionClass = 'mention', customEmoji = null } = {}) {
  if (!body) return '';

  const parsed = marked.parse(body);

  const clean = DOMPurify.sanitize(parsed, {
    ALLOWED_TAGS,
    ALLOWED_ATTR,
    // Block every URL scheme except the ones below — this is what stops
    // javascript: and data: payloads in links.
    ALLOWED_URI_REGEXP: /^(?:https?|mailto):/i,
  });

  const withMentions = highlightMentions(unwrapLoneParagraph(clean), mentionClass);
  return renderCustomEmoji(withMentions, customEmoji);
}

/**
 * marked wraps everything in <p>. For a one-paragraph message that block tag
 * breaks the chat bubble's inline layout (the trailing timestamp) and nests a
 * <p> inside the surrounding inline element, which is invalid HTML.
 *
 * Multi-block messages — code fences, lists, quotes — keep their structure.
 */
function unwrapLoneParagraph(html) {
  const template = document.createElement('template');
  template.innerHTML = html.trim();

  const children = template.content.children;
  if (children.length === 1 && children[0].tagName === 'P') {
    return children[0].innerHTML;
  }

  return template.innerHTML;
}
