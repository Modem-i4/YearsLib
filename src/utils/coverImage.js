const MAX_SOURCE_SIZE = 15 * 1024 * 1024
const MAX_IMAGE_EDGE = 1800
const WEBP_QUALITY = 0.86

const HEIF_BRANDS = new Set(['heic', 'heix', 'hevc', 'hevx', 'mif1', 'msf1'])

async function looksLikeHeif(file) {
  if (/^image\/(?:heic|heif)$/i.test(file.type) || /\.(?:heic|heif)$/i.test(file.name)) return true
  if (file.size < 12) return false

  const header = new Uint8Array(await file.slice(4, 12).arrayBuffer())
  const box = String.fromCharCode(...header.slice(0, 4))
  const brand = String.fromCharCode(...header.slice(4, 8))
  return box === 'ftyp' && HEIF_BRANDS.has(brand)
}

async function decodeImage(file) {
  try {
    return await createImageBitmap(file, { imageOrientation: 'from-image' })
  } catch (nativeError) {
    if (!await looksLikeHeif(file)) {
      throw new Error('Цей формат зображення не підтримується. Виберіть JPEG, PNG, WebP, AVIF, HEIC або HEIF.', { cause: nativeError })
    }

    try {
      const { heicTo } = await import('heic-to/csp')
      return await heicTo({ blob: file, type: 'bitmap' })
    } catch (heifError) {
      throw new Error('Не вдалося прочитати фото HEIC/HEIF. Спробуйте вибрати інше фото.', { cause: heifError })
    }
  }
}

function canvasToWebp(canvas) {
  return new Promise((resolve, reject) => {
    canvas.toBlob((blob) => {
      if (!blob || blob.type !== 'image/webp') {
        reject(new Error('Цей браузер не підтримує конвертацію зображень у WebP.'))
        return
      }
      resolve(new File([blob], 'cover.webp', { type: 'image/webp' }))
    }, 'image/webp', WEBP_QUALITY)
  })
}

export async function convertCoverToWebp(file) {
  if (file.size < 1) throw new Error('Вибране зображення порожнє.')
  if (file.size > MAX_SOURCE_SIZE) throw new Error('Вихідне зображення має бути меншим за 15 МБ.')

  const image = await decodeImage(file)
  const scale = Math.min(1, MAX_IMAGE_EDGE / Math.max(image.width, image.height))
  const canvas = document.createElement('canvas')
  canvas.width = Math.max(1, Math.round(image.width * scale))
  canvas.height = Math.max(1, Math.round(image.height * scale))

  try {
    const context = canvas.getContext('2d')
    if (!context) throw new Error('Браузер не зміг обробити зображення.')
    context.drawImage(image, 0, 0, canvas.width, canvas.height)
    return await canvasToWebp(canvas)
  } finally {
    image.close()
    canvas.width = 1
    canvas.height = 1
  }
}
