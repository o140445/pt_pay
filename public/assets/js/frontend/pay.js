define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'upload'], function ($, undefined, Backend, Table, Form) {

    var Controller = {

        index: function () {
            // copy
            $("#copy").on("click", function () {
                var clipboard = new ClipboardJS('.btn');

                // 复制成功时的提示
                clipboard.on('success', function (e) {
                    //test-msg-dark
                    Layer.msg(__('ID de transação copiado'));
                });
                // 复制失败时的提示
                clipboard.on('error', function (e) {
                });
            });
        }
    };
    return Controller;
});