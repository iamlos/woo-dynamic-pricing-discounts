jQuery(function($) {
    const $form   = $('#rule-form');
    const $inner  = $('#rule-form-inner');
    const $list   = $('#rules-list');
    const $addBtn = $('#add-new-rule');
    const $cancel = $('#cancel-rule');

    $addBtn.on('click', function(e) {
        e.preventDefault();
        $form.slideDown();
        $inner.trigger('reset');
        $inner.find('[name="rule_index"]').val('');
        toggleFields();
    });

    $cancel.on('click', function(e) {
        e.preventDefault();
        $form.slideUp();
    });

    $list.on('click', '.edit-rule', function(e) {
        e.preventDefault();
        const idx = $(this).closest('tr').data('index');
        $.post(wcDynamic.ajax_url, {
            action: 'wc_dynamic_get_rule',
            nonce: wcDynamic.nonce,
            index: idx
        }, function(res) {
            if (res.success) {
                populateForm(res.data, idx);
                $form.slideDown();
            }
        });
    });

    $list.on('click', '.delete-rule', function(e) {
        e.preventDefault();
        if (!confirm('Delete this rule?')) return;
        const idx = $(this).closest('tr').data('index');
        $.post(wcDynamic.ajax_url, {
            action: 'wc_dynamic_delete_rule',
            nonce: wcDynamic.nonce,
            index: idx
        }, function(res) {
            if (res.success) {
                renderTable(res.data.rules);
            }
        });
    });

    $inner.on('submit', function(e) {
        e.preventDefault();
        const data = $inner.serializeArray().reduce((o, i) => { o[i.name] = i.value; return o; }, {});
        $.post(wcDynamic.ajax_url, {
            action: 'wc_dynamic_save_rule',
            nonce: wcDynamic.nonce,
            rule: data
        }, function(res) {
            if (res.success) {
                renderTable(res.data.rules);
                $form.slideUp();
            }
        });
    });

    $(document).on('change', '.rule-type, .condition-select', toggleFields);
    function toggleFields() {
        const type = $('.rule-type').val();
        const cond = $('.condition-select').val();

        $('.target-row').toggle(type !== 'shipping');
        $('.cond-quantity, .cond-amount').toggle(cond === 'quantity' || cond === 'amount');
        $('.cond-role').toggle(cond === 'role');

        if (cond === 'role') {
            const roleVal = $('select[name="role"]').val();
            $('input[name="role"]').val(roleVal);
        }
    }

    function renderTable(rules) {
        $list.empty();
        if (!rules || rules.length === 0) {
            $list.append('<tr><td colspan="6">No rules yet. Click “Add New Rule” to start.</td></tr>');
            return;
        }
        rules.forEach((rule, idx) => {
            const target = rule.target === 'all' ? 'All Products' : ('Product ID: ' + rule.target);
            const cond = rule.condition === 'role' ? rule.role : (rule.condition === 'quantity' ? 'Qty ≥ ' + rule.min : 'Total ≥ ' + rule.min);
            const disc = rule.discount_type === 'percent' ? rule.discount_value + '%' : wc_price(rule.discount_value);
            $list.append(`
                <tr data-index="${idx}">
                    <td>${idx + 1}</td>
                    <td>${rule.type.charAt(0).toUpperCase() + rule.type.slice(1)}</td>
                    <td>${target}</td>
                    <td>${cond}</td>
                    <td>${disc}</td>
                    <td>
                        <a href="#" class="edit-rule">Edit</a> |
                        <a href="#" class="delete-rule" style="color:#a00;">Delete</a>
                    </td>
                </tr>
            `);
        });
    }

    function populateForm(rule, idx) {
        $inner.find('[name="rule_index"]').val(idx);
        $.each(rule, function(k, v) {
            const $el = $inner.find('[name="' + k + '"]');
            if ($el.is('select')) $el.val(v);
            else $el.val(v);
        });
        toggleFields();
    }

    // Initial load
    renderTable(wcDynamic.initial_rules);
});
