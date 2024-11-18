define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'upload'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Form.api.bindevent($("#add-form"), function (data, ret) {
                if (ret.code === 1) {
                    // 关闭tab页
                    // 手动关闭当前 Tab 页
                    parent.window.location.reload();
                }
            });
        },
        bindGoogle: function () {
            Controller.api.bindevent();
        },
    };
    return Controller;
});
