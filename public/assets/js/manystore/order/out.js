define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'order/out/index' + location.search,
                    add_url: 'order/out/add',
                    edit_url: 'order/out/edit',
                    del_url: 'order/out/del',
                    multi_url: 'order/out/multi',
                    import_url: 'order/out/import',
                    table: 'order_out',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                searchFormVisible: true,
                columns: [
                    [

                        {field: 'order_no', title: __('Order_no'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'member_order_no', title: __('Member_order_no'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'amount', title: __('Amount'), operate:false},
                        {field: 'actual_amount', title: __('Actual_amount'), operate:false},
                        {field: 'fee_amount', title: __('Fee_amount'), operate:false},
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.status,  searchList:{
                            "1":__('Status unpaid'),
                            "2":__('Status paid'),
                            "3":__('Status failed'),
                            "4":__('Status refund'),
                            "5":__('Status paying'),
                            }
                        },

                        {field: 'notify_status', title: __('Notify_status'), formatter: Table.api.formatter.status, searchList:{
                            "1":__('Notify_status success'),
                            "2":__('Notify_status fail'),
                            "0":__('Notify_status unknown'),
                            }
                        },
                        {field: 'notify_count', title: __('Notify_count'), operate: false},
                        {field: 'extra', title: __('收款信息'), operate: false, table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'error_msg', title: __('Error_msg'), operate: false, table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'e_no', title: __('E_no'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},


                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'pay_success_date', title: __('Pay_success_date'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},

                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
