// Disable the $ global alias completely
jQuery.noConflict();

var FieldPaletteBackend = window.FieldPaletteBackend || {};
var UtilsBundle = window.utilsBundle;

window.FieldPaletteBackend = FieldPaletteBackend;

FieldPaletteBackend.resolveRoot = function (root) {
    if (!root) {
        return document;
    }

    if (root.target) {
        root = root.target;
    }

    return document.id(root) || document;
};

FieldPaletteBackend.getElements = function (root, selector) {
    root = FieldPaletteBackend.resolveRoot(root);

    if (root.getElements) {
        return root.getElements(selector);
    }

    return $$(selector);
};

FieldPaletteBackend.deleteFieldPaletteEntry = function (el, id) {
    new Request.Contao({
        'url': el.href,
        'followRedirects': false,
        onComplete: function () {
            FieldPaletteBackend.refreshFieldPalette(el.getParent('.fielpalette-wizard').getProperty('id'));
        }
    }).get();

    return false;
};

FieldPaletteBackend.makeFieldPaletteListSortable = function (ul) {
    ul = document.id(ul);

    if (!ul || ul.retrieve('fieldpalette-sortable')) {
        return null;
    }

    var ds = new Scroller(document.getElement('body'), {
        onChange: function (x, y) {
            this.element.scrollTo(this.element.getScroll().x, y);
        }
    });

    var list = new Sortables(ul, {
        constrain: true,
        opacity: 0.6,
        onStart: function () {
            ds.start();
        },
        onComplete: function () {
            ds.stop();
        },
        handle: '.drag-handle'
    });

    list.active = false;

    list.addEvent('start', function () {
        list.active = true;
    });

    list.addEvent('complete', function (el) {
        if (!list.active) {
            return;
        }

        var handle = el.getElement('.tl_content_right ' + list.options.handle),
            href,
            id,
            pid;

        if (!handle) {
            return;
        }

        href = handle.get('data-href');
        id = handle.get('data-id');
        pid = handle.get('data-pid');

        if (el.getPrevious('li')) {
            pid = el.getPrevious('li').getChildren('.tl_content_right ' + list.options.handle).get('data-id');
            href = href.replace(/id=[0-9]*/, 'id=' + id) + '&act=cut&mode=1&pid=' + pid;
            new Request.Contao({'url': href, 'followRedirects': false}).get();
        }
        else if (el.getParent('ul')) {
            href = href.replace(/id=[0-9]*/, 'id=' + id) + '&act=cut&mode=2&pid=' + pid;
            new Request.Contao({'url': href, 'followRedirects': false}).get();
        }
    });

    ul.store('fieldpalette-sortable', {
        list: list,
        scroller: ds
    });

    return list;
};

FieldPaletteBackend.cleanupFieldPaletteListSortable = function (ul) {
    ul = document.id(ul);

    var sortable,
        elements;

    if (!ul) {
        return;
    }

    sortable = ul.retrieve('fieldpalette-sortable');

    if (!sortable) {
        return;
    }

    if (sortable.scroller && sortable.scroller.stop) {
        sortable.scroller.stop();
    }

    if (sortable.list) {
        sortable.list.active = false;
        elements = sortable.list.elements ? [].slice.call(sortable.list.elements) : [];

        elements.each(function (element) {
            var start = element.retrieve('sortables:start'),
                handle = sortable.list.options.handle ? element.getElement(sortable.list.options.handle) || element : element;

            if (!start || !handle) {
                return;
            }

            handle.removeEvents({
                mousedown: start,
                touchstart: start
            });
        });

        if (sortable.list.removeItems && elements.length) {
            sortable.list.removeItems(elements);
        }
    }

    ul.eliminate('fieldpalette-sortable');
};

/**
 * Open an iframe in a modal window
 *
 * @param {object} options An optional options object
 */
FieldPaletteBackend.openModalIframe = function (options) {
    var opt = options || {},
        maxWidth = (window.getSize().x - 20).toInt(),
        maxHeight = (window.getSize().y - 137).toInt();

    if (!opt.width || opt.width > maxWidth) {
        opt.width = Math.min(maxWidth, 900);
    }

    if (!opt.height || opt.height > maxHeight) {
        opt.height = maxHeight;
    }

    var M = new SimpleModal({
        'keyEsc': false, // see https://github.com/terminal42/contao-notification_center/issues/99
        'width': opt.width,
        'hideFooter': true,
        'draggable': false,
        'overlayOpacity': .5,
        'closeButton': true,
        'onShow': function () {
            document.body.setStyle('overflow', 'hidden');
        },
        'onHide': function () {
            FieldPaletteBackend.refreshFieldPalette(options.syncId);
            document.body.setStyle('overflow', 'auto');
        }
    });

    M.show({
        'title': opt.title,
        'contents': '<iframe src="' + opt.url + '" width="100%" height="' + opt.height + '" frameborder="0" id="fieldPaletteContent"></iframe>'
    });
};

FieldPaletteBackend.refreshFieldPalette = function (id) {
    var field = id.replace('ctrl_', '');

    new Request.Contao({
        onRequest: function () {
            var container = $(id),
                indicator = container ? container.getElement('.tl_fielpalette_indicator') : null;

            if (indicator) {
                indicator.show();
            }
        },
        onSuccess: function (txt, json) {
            var tmp,
                replacement,
                container;

            if (typeof json.autoSubmit !== 'undefined' && json.autoSubmit !== null && json.autoSubmit !== '') {
                Backend.autoSubmit(json.autoSubmit);
                return;
            }

            container = $(id);

            if (container && FieldPaletteBackend.cleanup) {
                FieldPaletteBackend.cleanup(container);
            }

            tmp = new Element('div', {html: json.content});
            replacement = tmp.getFirst();

            if (!replacement) {
                return;
            }

            replacement.replaces($(id));
            FieldPaletteBackend.initialize($(id));
        }
    }).post({'action': 'refreshFieldPaletteField', 'field': field, 'REQUEST_TOKEN': Contao.request_token});
};

// For jQuery scripts
(function ($) {
    FieldPaletteBackend.initDataTable = function (selector) {
        var $selector = $(selector),
            domElement = $selector.get(0),
            language = $('html').attr('lang'),
            rowOrder = false,
            table,
            messages = window.DATATABLE_MESSAGES || {};

        if (!domElement || !$.fn || !$.fn.dataTable) {
            return null;
        }

        if ($selector.hasClass('tl_fieldpalette_sortable')) {
            rowOrder = {
                selector: '.drag-handle'
            };
        }

        if ($selector.data('fieldpaletteDatatableInitialized')) {
            if ($.fn.dataTable.isDataTable(domElement)) {
                return $selector.DataTable();
            }

            return null;
        }

        if ($.fn.dataTable.isDataTable(domElement)) {
            table = $selector.DataTable();
        }
        else {
            table = $selector.DataTable({
                language: messages[language] ? messages[language] : messages['en'],
                stateSave: true,
                columnDefs: [
                    {
                        searchable: false,
                        targets: 0
                    },
                    {
                        targets: 'no-sort',
                        orderable: false
                    }
                ],
                rowReorder: rowOrder,
                pagingType: 'full_numbers',
                lengthMenu: [10, 25, 50, 100, 500],
                initComplete: function () {
                    var $wrapper = $selector.closest('.tl_fieldpalette_wrapper');

                    $wrapper.find('select').addClass('tl_select').css('width', 'auto');
                    $wrapper.find('input[type="search"], input[type="text"]').addClass('tl_text').css('width', 'auto');
                }
            });
        }

        $selector.data('fieldpaletteDatatableInitialized', true);

        table.off('row-reorder.fieldpalette');
        table.on('row-reorder.fieldpalette', function (e, diff, edit) {
            var currentID,
                currentDiff = null,
                handle,
                href,
                id,
                pid,
                prevRow,
                i;

            if (!rowOrder || !edit || !edit.triggerRow) {
                return;
            }

            currentID = edit.triggerRow.data()['DT_RowId'];

            for (i = 0; i < diff.length; i++) {
                if (diff[i].node.id === currentID) {
                    currentDiff = diff[i];
                    break;
                }
            }

            if (currentDiff === null) {
                return;
            }

            handle = $(currentDiff.node).find('.drag-handle');
            href = handle.data('href');
            id = handle.data('id');
            pid = handle.data('pid');

            if (currentDiff.newPosition === 0) {
                href = href.replace(/id=[0-9]*/, 'id=' + id) + '&act=cut&mode=2&pid=' + pid;
                new Request.Contao({'url': href, 'followRedirects': false}).get();
            }
            else {
                prevRow = $selector.find('#' + currentID).prev('tr');

                if (!prevRow.length) {
                    return;
                }

                pid = prevRow.find(rowOrder.selector).data('id');
                href = href.replace(/id=[0-9]*/, 'id=' + id) + '&act=cut&mode=1&pid=' + pid;
                new Request.Contao({'url': href, 'followRedirects': false}).get();
            }
        });

        return table;
    };

    FieldPaletteBackend.destroyDataTable = function (selector) {
        var $selector = $(selector),
            domElement = $selector.get(0),
            table;

        if (!domElement || !$.fn || !$.fn.dataTable) {
            return;
        }

        if ($.fn.dataTable.isDataTable(domElement)) {
            table = $selector.DataTable();
            table.off('row-reorder.fieldpalette');
            table.destroy();
        }

        $selector.removeData('fieldpaletteDatatableInitialized');
    };

    FieldPaletteBackend.initialize = function (root) {
        FieldPaletteBackend.getElements(root, 'table.tl_fieldpalette_wizard').each(function (table) {
            FieldPaletteBackend.initDataTable(table);
        });

        FieldPaletteBackend.getElements(root, 'ul.tl_fieldpalette_sortable').each(function (ul) {
            FieldPaletteBackend.makeFieldPaletteListSortable(ul);
        });
    };

    FieldPaletteBackend.cleanup = function (root) {
        FieldPaletteBackend.getElements(root, 'table.tl_fieldpalette_wizard').each(function (table) {
            FieldPaletteBackend.destroyDataTable(table);
        });

        FieldPaletteBackend.getElements(root, 'ul.tl_fieldpalette_sortable').each(function (ul) {
            FieldPaletteBackend.cleanupFieldPaletteListSortable(ul);
        });
    };

    FieldPaletteBackend.bindLifecycle = function () {
        if (FieldPaletteBackend.lifecycleBound) {
            return;
        }

        FieldPaletteBackend.lifecycleBound = true;

        window.addEvent('domready', function () {
            FieldPaletteBackend.initialize(document);
        });

        if (!document.documentElement || !document.documentElement.addEventListener) {
            return;
        }

        document.documentElement.addEventListener('turbo:render', function (event) {
            FieldPaletteBackend.initialize(event.target);
        });

        document.documentElement.addEventListener('turbo:frame-render', function (event) {
            FieldPaletteBackend.initialize(event.target);
        });

        document.documentElement.addEventListener('turbo:before-cache', function (event) {
            FieldPaletteBackend.cleanup(event.target);
        });
    };

    FieldPaletteBackend.bindLifecycle();
    FieldPaletteBackend.initialize(document);
})(jQuery);
