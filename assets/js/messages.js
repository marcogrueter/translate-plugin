/*
 * Scripts for the Messages controller.
 */
+function ($) { "use strict";

    var TranslateMessages = function() {
        var self = this;

        this.$form = null;
        this.tableToolbar = null;
        this.toInput = null;
        this.toHeader = null;
        this.foundHeader = null;
        this.tableElement = null;
        this.hideTranslated = false;
        this.emptyDataSet = null
        this.dataSet = null

        $(document).on('change', '#hideTranslated', function(){
            self.toggleTranslated($(this).is(':checked'));
            self.refreshTable();
        });

        $(document).on('keyup', '.control-table input.string-input', function(ev) {
            self.onApplyValue(ev);
        });

        this.toggleTranslated = function(isHide) {
            this.hideTranslated = isHide;
            this.setTitleContents();
        }

        this.setToolbarContents = function(tableToolbar) {
            if (tableToolbar) {
                this.tableToolbar = $(tableToolbar);
            }

            if (!this.tableElement) {
                return;
            }

            var $toolbar = $('.toolbar', this.tableElement);
            if ($toolbar.hasClass('message-buttons-added')) {
                return;
            }

            if (!this.tableToolbar.length) {
                return;
            }

            $toolbar.addClass('message-buttons-added');
            $toolbar.prepend(Mustache.render(this.tableToolbar.html()));
        }

        this.setTitleContents = function(fromEl, toEl, foundEl) {
            if (fromEl) {
                this.fromHeader = $(fromEl);
            }

            if (toEl) {
                this.toHeader = $(toEl);
            }

            if (foundEl) {
                this.foundHeader = $(foundEl);
            }

            if (!this.tableElement) {
                return;
            }

            if (!this.toHeader.length) {
                return;
            }

            var $headers = $('table.headers th', this.tableElement);
            $headers.eq(0).html(this.fromHeader.html());
            $headers.eq(1).html(Mustache.render(this.toHeader.html(), { hideTranslated: this.hideTranslated } ));
            $headers.eq(2).html(this.foundHeader.html());
        }

        this.setTableElement = function(el) {
            this.tableElement = $(el);
            this.$form = $('#messagesForm');
            this.toInput = this.$form.find('input[name=locale_to]');

            this.tableElement.one('oc.tableUpdateData', $.proxy(this.updateTableData, this));
        }

        this.onApplyValue = function(ev) {
            if (ev.keyCode == 13) {
                var $table = $(ev.currentTarget).closest('[data-control=table]');

                if (!$table.length) {
                    return;
                }

                var tableObj = $table.data('oc.table');
                if (tableObj) {
                    tableObj.setCellValue($(ev.currentTarget).closest('td').get(0), ev.currentTarget.value);
                    tableObj.commitEditedRow();
                }
            }
        }

        this.updateTableData = function(event, records) {
            if (this.hideTranslated && !records.length) {
                self.toggleTranslated($(this).is(':checked'));
                self.refreshTable();
            }
        }

        this.toggleDropdown = function(el) {
            setTimeout(function(){ $(el).dropdown('toggle') }, 1);
            return false;
        }

        this.setLanguage = function(code) {
            this.toInput.val(code);
            this.refreshGrid();
            return false;
        }

        this.refreshGrid = function() {
            this.$form.request('onRefresh');
        }

        this.refreshTable = function() {
            this.tableElement.table('updateDataTable');
        }
    }

    $.translateMessages = new TranslateMessages;

}(window.jQuery);
