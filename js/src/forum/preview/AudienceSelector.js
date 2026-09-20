/**
 * Ghost-preview audience simulation profiles.
 * ADMIN_ELEVATED_VISIBILITY_FOR_USER_PREVIEW=false
 */
export const AUDIENCE_PROFILES = ['guest', 'standard_member'];
export const DEFAULT_AUDIENCE = 'standard_member';

export function isElevatedAdminVisibilityAllowed() {
  return false;
}
