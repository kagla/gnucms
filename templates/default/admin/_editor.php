<?php
// GNUCMS_ID|capitalize: 첫 글자만 대문자, 나머지는 소문자.
$gnucmsCap = mb_strtoupper(mb_substr(GNUCMS_ID, 0, 1)) . mb_strtolower(mb_substr(GNUCMS_ID, 1));
$editor_upload_url = $this->url('admin.editor.images') . '?csrf_token=' . rawurlencode((string) $csrf_token) . '&image_key=' . rawurlencode((string) $values['image_key']);
$editor_discard_url = $this->url('admin.editor.images.discard') . '?csrf_token=' . rawurlencode((string) $csrf_token) . '&image_key=' . rawurlencode((string) $values['image_key']);
?>
<script src="<?= $this->e($this->base) ?>/vendor/ckeditor4/ckeditor.js"></script>
<script>
(function(){
  var textarea=document.querySelector('[data-cms-editor]');
  if(!textarea||!window.CKEDITOR){return}
  var root=document.documentElement,media=window.matchMedia('(prefers-color-scheme: dark)'),uploading=0,committed=false,discarded=false;
  var uploadUrl=<?= $this->json($editor_upload_url) ?>,discardUrl=<?= $this->json($editor_discard_url) ?>;
  var uploadedInput=textarea.form&&textarea.form.querySelector('[data-uploaded-images]'),uploadedFiles={};
  (uploadedInput&&uploadedInput.value?uploadedInput.value.split(','):[]).forEach(function(file){if(/^[a-f0-9]{32}\.(?:jpg|png|gif|webp)$/.test(file)){uploadedFiles[file]=true}});
  function rememberUpload(url){
    var match=String(url||'').match(/\/media\/editor\/[a-f0-9]{32}\/([a-f0-9]{32}\.(?:jpg|png|gif|webp))(?:[?#]|$)/i);
    if(!match){return}uploadedFiles[match[1].toLowerCase()]=true;
    if(uploadedInput){uploadedInput.value=Object.keys(uploadedFiles).join(',')}
  }
  function discardUploads(){
    if(committed||discarded){return}var files=Object.keys(uploadedFiles);if(!files.length){return}
    discarded=true;var body=new URLSearchParams();files.forEach(function(file){body.append('files[]',file)});
    if(navigator.sendBeacon&&navigator.sendBeacon(discardUrl,body)){return}
    fetch(discardUrl,{method:'POST',body:body,credentials:'same-origin',keepalive:true,
      headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}}).catch(function(){})
  }
  function dark(){return root.dataset.theme==='dark'||(!root.dataset.theme&&media.matches)}
  function syncTheme(editor){
    if(!editor.document){return}
    var body=editor.document.getBody(),html=editor.document.getDocumentElement();
    body.removeClass('<?= $this->e(GNUCMS_ID) ?>-editor-light');body.removeClass('<?= $this->e(GNUCMS_ID) ?>-editor-dark');
    html.removeClass('<?= $this->e(GNUCMS_ID) ?>-editor-light');html.removeClass('<?= $this->e(GNUCMS_ID) ?>-editor-dark');
    body.addClass(dark()?'<?= $this->e(GNUCMS_ID) ?>-editor-dark':'<?= $this->e(GNUCMS_ID) ?>-editor-light');
    html.addClass(dark()?'<?= $this->e(GNUCMS_ID) ?>-editor-dark':'<?= $this->e(GNUCMS_ID) ?>-editor-light');
  }
  function refreshStoredImages(editor){
    if(!editor.document){return}
    var images=editor.document.find('img');
    for(var i=0;i<images.count();i++){
      var image=images.getItem(i),src=image.getAttribute('src')||'';
      if(!/^\/media\/editor\//.test(src)){continue}
      var clean=src.split('?')[0];
      image.setAttribute('data-<?= $this->e(GNUCMS_ID) ?>-src',clean);image.addClass('<?= $this->e(GNUCMS_ID) ?>-image-loading');
      image.on('load',function(event){event.listenerData.removeClass('<?= $this->e(GNUCMS_ID) ?>-image-loading')},null,image);
      image.setAttribute('src',clean+'?editor_preview='+Date.now());
    }
  }
  function restoreImageUrls(editor){
    var images=editor.document.find('img[data-<?= $this->e(GNUCMS_ID) ?>-src]');
    for(var i=0;i<images.count();i++){
      var image=images.getItem(i);image.setAttribute('src',image.getAttribute('data-<?= $this->e(GNUCMS_ID) ?>-src'));
      image.removeAttribute('data-<?= $this->e(GNUCMS_ID) ?>-src');image.removeClass('<?= $this->e(GNUCMS_ID) ?>-image-loading');
    }
  }
  function insertImage(editor,url,name){
    var paragraph=new CKEDITOR.dom.element('p'),image=new CKEDITOR.dom.element('img');
    image.setAttributes({src:url,alt:name});paragraph.append(image);editor.insertElement(paragraph);
  }
  async function readUploadResponse(response){
    var text=await response.text(),data=null;
    try{data=text?JSON.parse(text):null}catch(error){}
    if(data&&data.uploaded&&data.url){return data}
    if(data&&data.error&&data.error.message){throw new Error(data.error.message)}
    var messages={
      401:'로그인이 필요하거나 로그인 시간이 만료되었습니다.',
      403:'이미지를 올릴 권한이 없거나 요청 확인 시간이 만료되었습니다. 화면을 새로고침해 주세요.',
      404:'이미지 업로드 주소를 찾을 수 없습니다.',
      413:'서버에서 허용한 업로드 용량을 초과했습니다.',
      422:'서버가 이미지 파일을 처리하지 못했습니다.',
      500:'서버가 이미지를 저장하지 못했습니다. 저장 공간과 폴더 권한을 확인해 주세요.'
    };
    var message=messages[response.status]||'서버가 올바른 업로드 응답을 보내지 않았습니다.';
    throw new Error(message+' (HTTP '+response.status+')');
  }
  function chooseImages(editor){
    var input=document.createElement('input');
    input.type='file';input.accept='image/jpeg,image/png,image/gif,image/webp';input.multiple=true;input.hidden=true;
    input.addEventListener('change',async function(){
      var files=Array.prototype.slice.call(input.files||[]);input.remove();
      if(!files.length){return}
      var valid=[],rejected=[];
      files.forEach(function(file){
        if(file.size>5*1024*1024){rejected.push(file.name+' (5MB 초과)')}
        else if(file.type&&!/^image\/(?:jpeg|png|gif|webp)$/.test(file.type)){rejected.push(file.name+' (지원하지 않는 형식)')}
        else{valid.push(file)}
      });
      if(!valid.length){editor.showNotification(rejected.join('<br>'),'warning');return}
      uploading++;
      var notice=editor.showNotification('사진 '+valid.length+'장을 올리는 중입니다.','progress',0),failed=[];
      for(var i=0;i<valid.length;i++){
        try{
          var formData=new FormData();formData.append('upload',valid[i],valid[i].name);
          var response=await fetch(uploadUrl,{method:'POST',body:formData,credentials:'same-origin',headers:{Accept:'application/json'}});
          var data=await readUploadResponse(response);
          rememberUpload(data.url);
          editor.focus();insertImage(editor,data.url,valid[i].name);
        }catch(error){
          var reason=error instanceof TypeError?'업로드 서버에 연결하지 못했습니다. 네트워크 상태를 확인해 주세요.':(error.message||'업로드 실패');
          failed.push(valid[i].name+': '+reason)
        }
        notice.update({message:'사진 '+valid.length+'장 중 '+(i+1)+'장 처리 중',progress:(i+1)/valid.length});
      }
      uploading--;
      if(rejected.length){failed=failed.concat(rejected)}
      if(failed.length){notice.update({message:'일부 사진을 올리지 못했습니다.<br>'+failed.map(CKEDITOR.tools.htmlEncode).join('<br>'),type:'warning',duration:7000})}
      else{notice.update({message:'사진 '+valid.length+'장을 본문에 넣었습니다.',type:'success',duration:3000})}
    });
    document.body.appendChild(input);input.click();
  }
  CKEDITOR.plugins.add('<?= $this->e(GNUCMS_ID) ?>imageupload',{init:function(editor){
    editor.addCommand('<?= $this->e(GNUCMS_ID) ?>ImageUpload',{exec:function(){chooseImages(editor)}});
    editor.ui.addButton('<?= $this->e($gnucmsCap) ?>Images',{label:'사진 올리기',command:'<?= $this->e(GNUCMS_ID) ?>ImageUpload',toolbar:'insert,5'});
  }});
  var editor=CKEDITOR.replace(textarea.id,{
    language:'ko',height:<?= max(120, min(800, (int) ($editor_height ?? 360))) ?>,versionCheck:false,resize_minWidth:0,
    /* 글·댓글 편집기와 같게, 엔터는 줄바꿈 하나다. 문단 사이를 벌리려면 엔터를 두 번 친다. */
    enterMode:CKEDITOR.ENTER_BR,shiftEnterMode:CKEDITOR.ENTER_BR,autoParagraph:false,
    contentsCss:[<?= $this->json($this->base . '/vendor/ckeditor4/contents.css') ?>,<?= $this->json($this->base . '/assets/editor-content.css?v=20260902-1') ?>],
    bodyClass:'<?= $this->e(GNUCMS_ID) ?>-editor-content',
    uploadUrl:uploadUrl,
    filebrowserImageUploadUrl:<?= $this->json($editor_upload_url . '&responseType=json') ?>,
    extraPlugins:'uploadimage,notification,<?= $this->e(GNUCMS_ID) ?>imageupload',
    extraAllowedContent:'img[alt,src,title]',
    removePlugins:'exportpdf,flash,forms,iframe,newpage,preview,print,save,scayt,sourcearea,templates',
    removeDialogTabs:'image:advanced;link:advanced',format_tags:'p;h2;h3;h4;pre',
    toolbar:[
      {name:'styles',items:['Format']},
      {name:'basicstyles',items:['Bold','Italic','Underline','Strike','RemoveFormat']},
      {name:'paragraph',items:['NumberedList','BulletedList','Blockquote','JustifyLeft','JustifyCenter','JustifyRight']},
      {name:'links',items:['Link','Unlink']},
      {name:'insert',items:['<?= $this->e($gnucmsCap) ?>Images','Table','HorizontalRule','SpecialChar']},
      {name:'history',items:['Undo','Redo']},
      {name:'tools',items:['Maximize']}
    ]
  });
  editor.on('instanceReady',function(){syncTheme(editor);refreshStoredImages(editor)});
  editor.on('fileUploadResponse',function(event){
    try{var data=JSON.parse(event.data.fileLoader.xhr.responseText||'{}');if(data.uploaded&&data.url){rememberUpload(data.url)}}catch(error){}
  });
  window.addEventListener('pagehide',discardUploads);
  new MutationObserver(function(){syncTheme(editor)}).observe(root,{attributes:true,attributeFilter:['data-theme']});
  if(media.addEventListener){media.addEventListener('change',function(){syncTheme(editor)})}
  if(textarea.form){textarea.form.addEventListener('submit',function(event){
    if(uploading){event.preventDefault();editor.showNotification('사진 업로드가 끝난 뒤 저장해 주세요.','warning');return}
    restoreImageUrls(editor);
    editor.updateElement();
    var text=editor.document.getBody().getText().replace(/\u00a0/g,' ').trim();
    if(<?= $this->json($editor_required ?? true) ?>&&!text&&editor.document.find('img').count()===0){event.preventDefault();editor.focus();alert('내용을 입력해 주세요.');return}
    committed=true;
  })}
})();
</script>
