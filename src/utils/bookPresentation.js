const coverPalettes = ['moss', 'coral', 'blue', 'ochre', 'berry']

export function coverPalette(book) {
  const stableValue = Number(book.id) || String(book.title).length
  return coverPalettes[stableValue % coverPalettes.length]
}

export function externalPdfUrl(value) {
  if (!value) return null

  try {
    const url = new URL(value)
    return url.protocol === 'https:' ? url.href : null
  } catch {
    return null
  }
}

export function externalViewer(value) {
  const url = externalPdfUrl(value)
  if (!url) return null

  const parsed = new URL(url)
  if (parsed.hostname === 'drive.google.com') {
    const fileMatch = parsed.pathname.match(/^\/file\/d\/([A-Za-z0-9_-]+)/)
    if (fileMatch) {
      return {
        type: 'google-drive',
        source: `https://drive.google.com/file/d/${fileMatch[1]}/preview`,
        externalUrl: url,
      }
    }
  }

  if (parsed.pathname.toLowerCase().endsWith('.pdf')) {
    return { type: 'pdf', source: url, externalUrl: url }
  }

  return { type: 'external', source: null, externalUrl: url }
}

export function usesEmbeddedViewer(value) {
  const viewer = externalViewer(value)
  return viewer?.type === 'google-drive' || viewer?.type === 'pdf'
}