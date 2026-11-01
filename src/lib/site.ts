import data from '../data/site.json';
export const site = data;
export const base = import.meta.env.BASE_URL;
export function imageUrl(url?: string | null) {
  if (!url) return '';
  return /^https?:\/\//.test(url) ? url : `${base}${url.replace(/^\//, '')}`;
}
export function contentHtml(html: string) { return html.replaceAll('__SITE_BASE__', base); }
