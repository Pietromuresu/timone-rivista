import './bootstrap';

import Alpine from 'alpinejs';
import Sortable from 'sortablejs';

window.Alpine = Alpine;

Alpine.directive('sortable', (el) => {
    Sortable.create(el, {
        animation: 150,
        handle: '.drag-handle',
        onEnd(evt) {
            if (evt.oldIndex === evt.newIndex) return;

            el.dispatchEvent(new CustomEvent('page-dropped', {
                detail: {
                    pageId: evt.item.dataset.pageId,
                    newPosition: evt.newIndex + 1,
                },
            }));
        },
    });
});

Alpine.start();
