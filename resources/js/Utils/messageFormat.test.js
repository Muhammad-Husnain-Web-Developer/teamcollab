import { describe, it, expect } from 'vitest';
import { renderMessageBody } from './messageFormat';

describe('renderMessageBody — sanitisation', () => {
  it('strips script tags', () => {
    const html = renderMessageBody('hello <script>alert(1)</script> world');
    expect(html).not.toContain('<script');
    expect(html).not.toContain('alert(1)');
  });

  it('strips inline event handlers', () => {
    const html = renderMessageBody('<img src=x onerror="alert(1)">');
    expect(html).not.toContain('onerror');
  });

  it('strips javascript: links', () => {
    const html = renderMessageBody('[click me](javascript:alert(1))');
    expect(html).not.toContain('javascript:');
  });

  it('strips data: URI links', () => {
    const html = renderMessageBody('[x](data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==)');
    expect(html).not.toContain('data:text/html');
  });

  it('strips iframes and style tags', () => {
    const html = renderMessageBody('<iframe src="//evil.test"></iframe><style>body{display:none}</style>');
    expect(html).not.toContain('<iframe');
    expect(html).not.toContain('<style');
  });

  it('escapes raw html so it renders as text, not markup', () => {
    const html = renderMessageBody('a <b>bold</b> attempt');
    // marked escapes raw inline HTML; the literal tag must not survive as markup
    expect(html).not.toContain('<b>');
  });
});

describe('renderMessageBody — formatting', () => {
  it('renders bold and italic', () => {
    expect(renderMessageBody('**bold**')).toContain('<strong>bold</strong>');
    expect(renderMessageBody('*italic*')).toContain('<em>italic</em>');
  });

  it('renders strikethrough', () => {
    expect(renderMessageBody('~~gone~~')).toContain('<del>gone</del>');
  });

  it('renders inline code', () => {
    expect(renderMessageBody('use `npm run dev`')).toContain('<code>npm run dev</code>');
  });

  it('renders fenced code blocks', () => {
    const html = renderMessageBody('```\nconst x = 1;\n```');
    expect(html).toContain('<pre>');
    expect(html).toContain('const x = 1;');
  });

  it('does not treat markdown inside a code block as formatting', () => {
    const html = renderMessageBody('```\n**not bold**\n```');
    expect(html).not.toContain('<strong>');
  });

  it('renders lists and blockquotes', () => {
    expect(renderMessageBody('- one\n- two')).toContain('<li>');
    expect(renderMessageBody('> quoted')).toContain('<blockquote>');
  });

  it('keeps a single-line message inline (no wrapping paragraph)', () => {
    // The chat bubble puts the timestamp on the same line, which a block <p>
    // would break.
    expect(renderMessageBody('just text')).toBe('just text');
  });

  it('forces links to open safely in a new tab', () => {
    const html = renderMessageBody('[site](https://example.com)');
    expect(html).toContain('target="_blank"');
    expect(html).toContain('rel="noopener noreferrer nofollow"');
  });
});

describe('renderMessageBody — mentions', () => {
  it('wraps mentions with the given class', () => {
    const html = renderMessageBody('hi @hamid', { mentionClass: 'mention-x' });
    expect(html).toContain('class="mention-x"');
    expect(html).toContain('@hamid');
  });

  it('leaves mentions inside code blocks alone', () => {
    const html = renderMessageBody('```\n@hamid\n```', { mentionClass: 'mention-x' });
    expect(html).not.toContain('mention-x');
  });

  it('does not inject markup from the mention text itself', () => {
    const html = renderMessageBody('@<script>alert(1)</script>');
    expect(html).not.toContain('<script');
  });

  it('returns an empty string for an empty body', () => {
    expect(renderMessageBody('')).toBe('');
    expect(renderMessageBody(null)).toBe('');
  });
});
