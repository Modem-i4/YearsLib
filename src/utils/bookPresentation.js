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