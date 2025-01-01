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
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'order_no', title: __('Order_no'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'member_id', title: __('Member_id')},
                        {field: 'member_order_no', title: __('Member_order_no'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'channel_order_no', title: __('Channel_order_no'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'amount', title: __('Amount'), operate:false},
                        {field: 'actual_amount', title: __('Actual_amount'), operate:false},
                        {field: 'fee_amount', title: __('Fee_amount'), operate:false},
                        {field: 'project_id', title: __('Project_id')},
                        {field: 'channel_id', title: __('Channel_id')},
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.status,  searchList:{
                            "1":__('Status unpaid'),
                            "2":__('Status paid'),
                            "3":__('Status failed'),
                            "4":__('Status refund'),
                            "5":__('Status paying'),
                            }
                        },
                        {field: 'area_id', title: __('Area_id'), searchList: $.getJSON('order/out/getAreaList'), visible: false},
                        {field: 'area.name', title: __('Area_id'), operate: false, formatter: Table.api.formatter.label},

                        {field: 'notify_status', title: __('Notify_status'), formatter: Table.api.formatter.status, searchList:{
                            "1":__('Notify_status success'),
                            "2":__('Notify_status fail'),
                            "0":__('Notify_status unknown'),
                            }
                        },
                        {field: 'notify_count', title: __('Notify_count'), operate: false},
                        {field: 'extra', title: __('提交信息'), operate: false, table: table, class: 'autocontent', formatter: Table.api.formatter.content},

                        {field: 'error_msg', title: __('Error_msg'), operate: false, table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'e_no', title: __('E_no'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},

                        {field: 'pay_success_date', title: __('Pay_success_date'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},

                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        // 解冻按钮
                        {
                            field: 'buttons',
                            operate: false,
                            title: __('操作'),
                            table: table,
                            events: Table.api.events.operate,
                            buttons: [
                                // 订单完成
                                {
                                    name: 'ajax',
                                    text: __('订单完成'),
                                    title: __('订单完成'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                    icon: 'fa fa-magic',
                                    url: 'order/out/complete',
                                    confirm: "确认: 订单完成", //提示文字
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

                                // 订单失败
                                {
                                    name: 'ajax',
                                    text: __('订单失败'),
                                    title: __('订单失败'),
                                    classname: 'btn btn-xs btn-danger btn-magic btn-ajax',
                                    icon: 'fa fa-magic',
                                    url: 'order/out/fail',
                                    confirm: "确认: 订单失败", //提示文字
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
                                // 退款
                                {
                                    name: 'ajax',
                                    text: __('退款'),
                                    title: __('退款'),
                                    classname: 'btn btn-xs btn-danger btn-magic btn-ajax',
                                    icon: 'fa fa-magic',
                                    url: 'order/out/refund',
                                    confirm: "确认: 退款", //提示文字
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

                                // 发送通知
                                {
                                    name: 'ajax',
                                    text: __('发送通知'),
                                    title: __('发送通知'),
                                    classname: 'btn btn-xs btn-info btn-magic btn-ajax',
                                    icon: 'fa fa-magic',
                                    url: 'order/out/notify',
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

                                // 通知列表
                                {
                                    name: 'detail',
                                    text: __('通知列表'),
                                    title: __('通知列表'),
                                    classname: 'btn btn-xs btn-info btn-magic btn-dialog',
                                    icon: 'fa fa-magic',
                                    url: 'order/notify?order_no={order_no}',
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
                            formatter: Table.api.formatter.buttons
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
