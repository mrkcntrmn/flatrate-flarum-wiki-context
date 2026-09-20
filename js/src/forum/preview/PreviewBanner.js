/**
 * Admin ghost-preview banner contract (operator cue only).
 * Authorization remains server-side.
 *
 * Expected copy:
 *   ADMIN GHOST PREVIEW
 *   Not visible to members
 *   Read-only
 *   Audience: Standard Member
 *   Graph: <short version>
 */
export default class PreviewBanner {
  static contract() {
    return {
      title: 'ADMIN GHOST PREVIEW',
      notVisibleToMembers: true,
      readOnly: true,
      defaultAudienceLabel: 'Standard Member',
      authorization: 'server-side',
    };
  }
}
