(function($) {
    $(document).ready(function() {

        const LEVELS_SELECT_ALL = '#wawp_check_all_levels';
        const LEVELS_CHECKBOXES = '.wawp_case_level';

        const GROUPS_SELECT_ALL = '#wawp_check_all_groups';
        const GROUPS_CHECKBOXES = '.wawp_case_group';

        // on page load, select 'select all' box if all checkboxes are selected
        toggle_select_all_check(LEVELS_SELECT_ALL, LEVELS_CHECKBOXES);
        toggle_select_all_check(GROUPS_SELECT_ALL, GROUPS_CHECKBOXES);
        
        // Check/uncheck all levels
        $(LEVELS_SELECT_ALL).click(function () {
            toggle_check(LEVELS_SELECT_ALL, LEVELS_CHECKBOXES);
            
        });

        // Check/uncheck all groups
        $(GROUPS_SELECT_ALL).click(function () {
            toggle_check(GROUPS_SELECT_ALL, GROUPS_CHECKBOXES);
        });

        // If all checkboxes are selected, check the select-all checkbox, and vice versa
        // Levels
        $(LEVELS_CHECKBOXES).click(function() {
            toggle_select_all_check(LEVELS_SELECT_ALL, LEVELS_CHECKBOXES);
        });

        // Groups
        $(GROUPS_CHECKBOXES).click(function() {
            toggle_select_all_check(GROUPS_SELECT_ALL, GROUPS_CHECKBOXES);
        });

        // enable tab on custom css textarea
        $('.wawp_user_style_input').keydown(function(e) {
            if(e.keyCode === 9) { // tab was pressed
                // get caret position/selection
                var start = this.selectionStart;
                var end = this.selectionEnd;
        
                var $this = $(this);
                var value = $this.val();
        
                // set textarea value to: text before caret + tab + text after caret
                $this.val(value.substring(0, start)
                            + "\t"
                            + value.substring(end));
        
                // put caret at right position again (add one for the tab)
                this.selectionStart = this.selectionEnd = start + 1;
        
                // prevent the focus lose
                e.preventDefault();
            }
        });
    })

    function toggle_check(select_all, selector) {
        if ($(select_all).is(':checked')) {
            $(selector).prop('checked', true);
        } else {
            $(selector).prop('checked', false);
        }
    }

    function toggle_select_all_check(select_all, selector) {
        if ($(selector + ':checked').length == $(selector).length) {
            $(select_all).prop('checked', true);
        } else {
            $(select_all).prop('checked', false);
        }
    }
}) (jQuery);
