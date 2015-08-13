<h2 class="panel-title"><?= h($panel->title) ?><span id="panel-close" class="button-close">&times;</span></h2>
<div class="panel-content" id="panel-main-content">
    <?= $this->element($panel->element) ?>
</div>

<div id="openModal" class="modalDialog">
    <div>
        <a href="#modalClose" title="Close" class="modalClose">X</a>
        <h2>JSON</h2>
        <div class="modalContent">
            <textarea></textarea>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        var baseUrl = '<?= $this->Url->build([
            'plugin' => 'DebugKit',
            'controller' => 'Toolbar',
            'action' => 'getVariableAsJson'
        ]); ?>';

        function showMessage(el, text) {
            el.show().text(text).fadeOut(2000);
        }

        $('.get-as-json').on('click', function(e) {
            var el = $(this);
            var name = el.data('name');
            var id = $('#toolbar li.panel-active').data('id');
            var messageEl = el.parent().find('.inline-message');

            var xhr = $.ajax({
                url: baseUrl + '/' + name + '/' + id,
                dataType: 'json',
                type: 'GET'
            });
            xhr.done(function(response) {
                $('#openModal h2').text('JSON - ' + name);
                $('#openModal textarea').val(response.content);
                $('#openModal').css({display: 'block'});
            }).error(function(response) {
                showMessage(messageEl, name + ' could not get variable as json.');
            });
            e.preventDefault();
        });

        $('.modalClose').click(function() { $('#openModal').css({display: 'none'}); });
    });
</script>