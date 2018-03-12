// Disable the $ global alias completely
jQuery.noConflict();

var FieldPaletteBackend = {};

FieldPaletteBackend.deleteFieldPaletteEntry = function (el, id) {
    new Request.Contao({
        'url': el.href,
        'followRedirects': false,
        onComplete: function (txt, json) {
            FieldPaletteBackend.refreshFieldPalette(el.getParent('.fielpalette-wizard').getProperty('id'));
        }
    }).get();

    return false;
};


FieldPaletteBackend.makeFieldPaletteListSortable = function (ul) {
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
        var id, pid, req, href;

        var handle = el.getElement('.tl_content_right ' + list.options.handle),
            href = handle.get('data-href'),
            id = handle.get('data-id'),
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
};

/**
 * Open an iframe in a modal window
 *
 * @param {object} options An optional options object
 */
FieldPaletteBackend.openModalIframe = function (options) {
    var opt = options || {};
    var max = (window.getSize().y - 180).toInt();
    if (!opt.height || opt.height > max) {
        opt.height = max;
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

    // save and close handling within tl_content editing
    document.getElementById('fieldPaletteContent').onload = function () {

        var form = (this.contentDocument.body || this.contentWindow.document.body).getElementById('tl_fieldpalette'),
            container = (this.contentDocument.body || this.contentWindow.document.body).getElementById('container');

        if (container != null) {
            container.classList.add("fieldpalette-form-container");
        }

        // fallback if something went wrong within contao referrer session handling
        if (form == null) {
            return false;
        }

        var hasError = form.querySelectorAll('.tl_error').length > 0;

        var url = form.getAttribute('action'),
            closeModal = HASTE_PLUS.getParameterByName('closeModal', url);

        if (closeModal && !hasError) {
            M.hide();
        }
        else {
            url = HASTE_PLUS.removeParameterFromUri(url, 'closeModal');
            form.setAttribute('action', url);
        }

        form.getElementById('saveNclose').addEventListener("click", function () {
            url = HASTE_PLUS.addParameterToUri(url, 'closeModal', 1);
            form.setAttribute('action', url);
        });

    };
};


FieldPaletteBackend.refreshFieldPalette = function (id) {

    var field = id.replace('ctrl_', '');

    new Request.Contao({
        onRequest: function () {
            $(id).getElement('.tl_fielpalette_indicator').show();
        },
        onSuccess: function (txt, json) {
            var tmp = new Element('div', {html: json.content});
            tmp.getFirst().replaces($(id));
            $(id).getElement('.tl_fielpalette_indicator').hide();

            FieldPaletteBackend.initDataTable($(id).getElement('table.tl_fieldpalette_wizard'));
            FieldPaletteBackend.makeFieldPaletteListSortable($(id).getElement('ul.tl_fieldpalette_sortable'));
        }
    }).post({'action': 'refreshFieldPaletteField', 'field': field, 'REQUEST_TOKEN': Contao.request_token});

};

$$('ul.tl_fieldpalette_sortable').each(function (ul) {
    FieldPaletteBackend.makeFieldPaletteListSortable(ul.id);
});


// For jQuery scripts
(function ($) {

    FieldPaletteBackend.initDataTable = function (selector) {
        var language = $('html').attr('lang');

        var rowOrder = false;

        if ($(selector).hasClass('tl_fieldpalette_sortable')) {
            rowOrder = {
                selector: '.drag-handle'
            };
        }

        var table;

        if ($.fn.dataTable.isDataTable(selector)) {
            table = $(selector).DataTable();
        }
        else {
            table = $(selector).DataTable({
                language: DATATABLE_MESSAGES[language] ? DATATABLE_MESSAGES[language] : DATATABLE_MESSAGES['en'],
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
                    var $wrapper = $(selector).closest('.tl_fieldpalette_wrapper');

                    $wrapper.find('select').addClass('tl_select').css('width', 'auto');
                    $wrapper.find('input[type="search"], input[type="text"]').addClass('tl_text').css('width', 'auto');
                }
            });
        }


        table.on('row-reorder', function (e, diff, edit) {
            var currentID = edit.triggerRow.data()['DT_RowId'],
                currentDiff = null;

            for (var i = 0, ien = diff.length; i < ien; i++) {
                if (diff[i].node.id == currentID) {
                    currentDiff = diff[i];
                    break;
                }
            }

            if (currentDiff === null) {
                return;
            }

            var handle = $(currentDiff.node).find('.drag-handle'),
                href = handle.data('href'),
                id = handle.data('id'),
                pid = handle.data('pid');

            if (currentDiff.newPosition === 0) {
                href = href.replace(/id=[0-9]*/, 'id=' + id) + '&act=cut&mode=2&pid=' + pid;
                new Request.Contao({'url': href, 'followRedirects': false}).get();
            }
            else {

                var prevRow = $(selector).find('#' + currentID).prev('tr');

                if (typeof prevRow === 'undefined') {
                    return;
                }

                pid = prevRow.find(rowOrder.selector).data('id');
                href = href.replace(/id=[0-9]*/, 'id=' + id) + '&act=cut&mode=1&pid=' + pid;
                new Request.Contao({'url': href, 'followRedirects': false}).get();
            }
        });
    };

    $(document).ready(function () {
        $('table.tl_fieldpalette_wizard').each(function () {
            FieldPaletteBackend.initDataTable(this);
        });
    });

})(jQuery);

