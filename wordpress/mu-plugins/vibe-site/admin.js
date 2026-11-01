jQuery(function ($) {
  $('.vibe-media').on('click', function () {
    const prefix = $(this).data('prefix');
    const picker = wp.media({ title: '选择网站图片', library: { type: 'image' }, multiple: false, button: { text: '使用这张图片' } });
    picker.on('select', function () {
      const item = picker.state().get('selection').first().toJSON();
      $('#vibe-' + prefix + '-image').val(item.id);
      $('#vibe-' + prefix + '-preview').attr('src', item.url).show();
    });
    picker.open();
  });
  $('.vibe-media-remove').on('click', function () {
    const prefix = $(this).data('prefix');
    $('#vibe-' + prefix + '-image').val('0');
    $('#vibe-' + prefix + '-preview').attr('src', '').hide();
  });
});
