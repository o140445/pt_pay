define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'payment/channel/index',
                    add_url: 'payment/channel/add',
                    edit_url: 'payment/channel/edit',
                    del_url: 'payment/channel/del',
                    multi_url: 'payment/channel/multi',
                    table: 'channel',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'), sortable: true},
                        {field: 'title', title: __('Title'), operate: 'LIKE', },
                        {field: 'code', title: __('Code')},
                        {field: 'sign', title: __('唯一标识')},
                        {field: 'mch_id', title: __('MchId'), operate: false },
                        {field: 'config_area.name', title: __('地区'), operate: false },
                        {field: 'status', title: __('Status'), searchList: {0:'禁用',1:'正常'}, formatter: Table.api.formatter.toggle},
                        {field: 'is_in', title: __('IsIn'), searchList: {0:__('Disabled'),1:__('Enabled')}, formatter: Table.api.formatter.status},
                        {field: 'is_out', title: __('IsOut'), searchList: {0:__('Disabled'),1:__('Enabled')}, formatter: Table.api.formatter.status},
                        {field: 'create_time', title: __('CreateTime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {field: 'update_time', title: __('UpdateTime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            // 跟据支付方式显示对应的配置
             $(document).on("change", "select[name='row[code]']", function () {
                    var code = $(this).val();
                    var form = $(this).closest('form');
                    if (code) {
                        Fast.api.ajax({
                            url: 'payment/channel/config',
                            type: 'GET',
                            data: {code: code},
                            dataType: 'json',

                        }, function (data, ret) {
                            if (data.length === 0) {
                                form.find('.extra').html('');
                                return;
                            }
                            console.log(data);
                            var html = '';
                            for (var i in data) {
                                html += '<div class="form-group">' +
                                        '<label class="col-xs-12 col-sm-2 control-label">' + data[i].name + '</label>' +
                                        '<div class="col-xs-12 col-sm-8">' +
                                            '<input type="text" class="form-control" data-rule="required" name="row[extra][' + i + '][value]" value="' + data[i].value + '">' +
                                            '<input type="text" style="display: none" class="form-control" data-rule="required" name="row[extra][' + i + '][key]" value="' + data[i].key + '">' +
                                            '<input type="text" style="display: none" class="form-control" data-rule="required" name="row[extra][' + i + '][name]" value="' + data[i].name + '">' +
                                        '</div>' +
                                    '</div>';
                            }
                            form.find('.extra').html(html);
                        });
                    }
                });


            Controller.api.bindevent();

        },
        edit: function () {

            // 跟据支付方式显示对应的配置
            $(document).on("change", "select[name='row[code]']", function () {
                    var code = $(this).val();
                    var form = $(this).closest('form');

                    // 清空当前的渠道选项
                    form.find('.extra').html('');

                    if (code) {
                        Fast.api.ajax({
                            url: 'payment/channel/config',
                            type: 'GET',
                            data: {code: code},
                            dataType: 'json',

                        }, function (data, ret) {
                            if (data.length === 0) {
                                form.find('.extra').html('');
                                return;
                            }
                            console.log(data);
                            var html = '';
                            for (var i in data) {
                                html += '<div class="form-group">' +
                                        '<label class="col-xs-12 col-sm-2 control-label">' + data[i].name + '</label>' +
                                        '<div class="col-xs-12 col-sm-8">' +
                                            '<input type="text" class="form-control" data-rule="required" name="row[extra][' + i + '][value]" value="' + data[i].value + '">' +
                                            '<input type="text" style="display: none" class="form-control" data-rule="required" name="row[extra][' + i + '][key]" value="' + data[i].key + '">' +
                                            '<input type="text" style="display: none" class="form-control" data-rule="required" name="row[extra][' + i + '][name]" value="' + data[i].name + '">' +
                                        '</div>' +
                                    '</div>';
                            }
                            form.find('.extra').html(html);
                        });
                    }
                });


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