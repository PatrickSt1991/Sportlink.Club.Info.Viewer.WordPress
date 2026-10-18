import noImage from '@/assets/no_image.svg';
import { logoUrl } from './wpClient';

/**
 * Same signature as the viewer's logo loader so the processors are shared
 * verbatim. In WordPress the server caches the image; the browser just needs a URL.
 */
export async function fetchTeamLogo(bucket, hash) {
  if (!bucket || !hash) return noImage;
  return logoUrl(bucket, hash);
}
