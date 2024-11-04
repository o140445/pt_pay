define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'order/in/index' + location.search,
                    add_url: 'order/in/add',
                    edit_url: 'order/in/edit',
                    del_url: 'order/in/del',
                    multi_url: 'order/in/multi',
                    import_url: 'order/in/import',
                    table: 'order_in',
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
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'member_id', title: __('Member_id')},
                        {field: 'order_no', title: __('Order_no'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'member_order_no', title: __('Member_order_no'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'channel_order_no', title: __('Channel_order_no'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},

                        {field: 'status', title: __('Status'),  formatter: Table.api.formatter.label, searchList: {
                                "1": __('Status unpaid'),
                                "2": __('Status paid'),
                                "3": __('Status failed')
                            }
                        },

                        {field: 'amount', title: __('Amount'), operate:false},
                        {field: 'actual_amount', title: __('Actual_amount'), operate:false},
                        {field: 'fee_amount', title: __('Fee_amount'), operate:false},
                        {field: 'project_id', title: __('Project_id')},
                        {field: 'channel_id', title: __('Channel_id')},
                        {field: 'area_id', title: __('Area_id'),  searchList: $.getJSON('member/config/area/list'), visible: false},
                        {field: 'area.name', title: __('Area'), formatter:Table.api.formatter.label, operate: false },

                        {
                            field: 'notify_status', title: __('Notify_status'), searchList: {
                                "1": __('Notify_status success'),
                                "2": __('Notify_status fail'),
                                "0": __('Notify_status unknown'),
                            },
                            formatter:Table.api.formatter.label,
                        },

                        {field: 'notify_count', title: __('Notify_count'), operate: false},
                        {field: 'e_no', title: __('E_no'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'error_msg', title: __('Error_msg'), operate: false, table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'pay_success_date', title: __('Pay_success_date'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            // 订单完成按钮
                            buttons: [
                                {
                                    name: 'ajax',
                                    text: __('Order Complete'),
                                    title: __('Order Complete'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                    icon: 'fa fa-magic',
                                    url: 'order/in/complete',
                                    confirm: "确认: 设置订单完成", //提示文字
                                    success: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        //刷新表格
                                        table.bootstrapTable('refresh');
                                    },
                                    error: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        return false;
                                    }
                                },

                                // 订单失败按钮
                                {
                                    name: 'ajax',
                                    text: __('Order Fail'),
                                    title: __('Order Fail'),
                                    classname: 'btn btn-xs btn-danger btn-magic btn-ajax',
                                    icon: 'fa fa-magic',
                                    url: 'order/in/fail',
                                    confirm: "确认: 设置订单失败", //提示文字
                                    success: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        //刷新表格
                                        table.bootstrapTable('refresh');
                                    },
                                    error: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        return false;
                                    }
                                },

                                // 发送通知按钮
                                {
                                    name: 'ajax',
                                    text: __('Send Notify'),
                                    title: __('Send Notify'),
                                    classname: 'btn btn-xs btn-info btn-magic btn-ajax',
                                    icon: 'fa fa-magic',
                                    url: 'order/in/notify',
                                    confirm: "确认: 发送通知", //提示文字
                                    success: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        //刷新表格
                                        table.bootstrapTable('refresh');
                                    },
                                    error: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        return false;
                                    }
                                },

                                // 打开通知详情按钮
                                {
                                    name: 'detail',
                                    text: __('Notify Detail'),
                                    title: __('Notify Detail'),
                                    classname: 'btn btn-xs btn-info btn-magic btn-dialog',
                                    icon: 'fa fa-magic',
                                    url: 'order/in/notify/detail',
                                    success: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        //刷新表格
                                        table.bootstrapTable('refresh');
                                    },
                                    error: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        return false;
                                    }
                                },
                            ],
                        }
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