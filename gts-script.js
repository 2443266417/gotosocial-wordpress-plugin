jQuery(document).ready(function($){

  // 图片放大
  $('#gts-feed').magnificPopup({
    delegate: 'a.gts-image-link',
    type: 'image',
    gallery: { enabled: true },
    mainClass: 'mfp-fade',
    removalDelay: 300,
  });

  // 加载更多说说
  $('#gts-load-more').on('click', function(){
    var $btn = $(this);
    $btn.prop('disabled', true).text('加载中...');
    var $last = $('#gts-feed .gts-status').last();
    var max_id = $last.data('id');

    $.post(gts_ajax.ajax_url, {
      action: 'gts_load_more',
      max_id: max_id
    }, function(response){
      if(response.trim().length === 0){
        $btn.text('没有更多了');
        $btn.prop('disabled', true);
      } else {
        $('#gts-feed').append(response);
        $btn.prop('disabled', false).text('加载更多');
      }
    }).fail(function(){
      alert('加载失败，请稍后重试');
      $btn.prop('disabled', false).text('加载更多');
    });
  });

  // 查看更多评论（示例，需服务端支持，当前仅演示）
  $('#gts-feed').on('click', '.gts-load-comments', function(){
    var $btn = $(this);
    var status_id = $btn.data('id');
    $btn.prop('disabled', true).text('加载中...');
    
    // 这里可以发起AJAX请求加载更多评论
    // 目前服务端接口未实现分页加载评论，示例仅提示
    setTimeout(function(){
      alert('加载更多评论功能暂未实现');
      $btn.prop('disabled', false).text('查看更多评论');
    }, 800);
  });

});
