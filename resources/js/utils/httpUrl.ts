/** Match the absolute HTTP(S) destination contract before generating checkout HTML. */
export function isAbsoluteHttpUrl(value: string): boolean {
  if (
    value.length > 2048 ||
    !/^https?:\/\//i.test(value) ||
    /[\s\\]/.test(value) ||
    Array.from(value).some(character => character.charCodeAt(0) < 32 || character.charCodeAt(0) === 127)
  ) {
    return false;
  }

  try {
    const url = new URL(value);
    return (url.protocol === 'http:' || url.protocol === 'https:') && url.hostname !== '';
  } catch {
    return false;
  }
}
