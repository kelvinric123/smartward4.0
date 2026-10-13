// SortableJS (drag to reorder) for Ward Settings, bundled so it works on a
// hospital network with no internet. The page uses the global Sortable, as the
// CDN script gave it. List it before resources/js/app.js in @vite, so it is
// there when Alpine starts the component that uses it.
import Sortable from 'sortablejs';

window.Sortable = Sortable;
