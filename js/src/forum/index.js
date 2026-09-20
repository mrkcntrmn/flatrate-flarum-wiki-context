import app from 'flarum/forum/app';
import BrowsePage from './components/BrowsePage';
import addWikiComposer from './addWikiComposer';
import addWikiDiscussionPresentation from './addWikiDiscussionPresentation';

/**
 * WIKI-001F public browse routes + context UI.
 *
 * Server-side middleware remains authoritative. Registering the client route
 * does not open access while browse/public rollout gates are closed.
 */
app.initializers.add('flatrate-wiki-context', () => {
  app.routes['flatrate-wiki.browse'] = {
    path: '/browse/:id',
    component: BrowsePage,
  };

  app.routes['flatrate-wiki.browse.slug'] = {
    path: '/browse/:id/:slug',
    component: BrowsePage,
  };

  addWikiComposer();
  addWikiDiscussionPresentation();
});
