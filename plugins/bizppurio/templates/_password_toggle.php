<script>
(function(){
  var button=document.getElementById('password-toggle'),field=document.getElementById('password');
  if(!button||!field){return}
  var form=field.form,error=document.getElementById('password-error'),stored=false,loading=false,serial=0;
  function state(show){
    field.type=show?'text':'password';
    button.setAttribute('aria-pressed',show?'true':'false');
    button.title=show?'비밀번호 숨기기':'비밀번호 표시';
    button.setAttribute('aria-label',button.title);
  }
  function hide(){
    serial++;state(false);
    if(stored){field.value='';stored=false}
  }
  field.addEventListener('input',function(){serial++;stored=false});
  form.elements.account.addEventListener('input',hide);
  form.addEventListener('submit',hide);
  window.addEventListener('pagehide',hide);
  document.addEventListener('visibilitychange',function(){if(document.hidden){hide()}});
  button.hidden=false;
  button.addEventListener('click',async function(){
    error.hidden=true;
    if(field.type==='text'){hide();field.focus();return}
    if(field.value!==''||button.dataset.passwordSet!=='1'){state(true);field.focus();return}
    if(loading){return}
    loading=true;button.disabled=true;
    var requestSerial=++serial;
    try{
      var body=new URLSearchParams({action:'reveal-password',environment:form.elements.environment.value,
        account:form.elements.account.value,revision:button.dataset.revision,csrf_token:form.elements.csrf_token.value});
      // name="action"인 저장 버튼이 form.action 속성을 가리므로 HTML 속성을 직접 읽는다.
      var response=await fetch(form.getAttribute('action'),{method:'POST',credentials:'same-origin',cache:'no-store',
        headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','Accept':'application/json'},body:body.toString()});
      if(!response.ok){throw new Error('request failed')}
      var data=await response.json();
      if(requestSerial!==serial){return}
      if(typeof data.password!=='string'){throw new Error('invalid response')}
      field.value=data.password;stored=true;state(true);field.focus();
    }catch(e){
      if(requestSerial===serial){error.textContent='비밀번호를 확인하지 못했습니다. 계정 설정과 로그인 상태를 확인한 뒤 다시 시도해 주세요.';error.hidden=false}
    }finally{loading=false;button.disabled=false}
  });
})();
(function(){
  var button=document.getElementById('senderkey-toggle'),field=document.getElementById('senderkey');
  if(!button||!field){return}
  function state(show){
    field.type=show?'text':'password';
    button.setAttribute('aria-pressed',show?'true':'false');
    button.title=show?'발신프로필 키 숨기기':'발신프로필 키 표시';
    button.setAttribute('aria-label',button.title);
  }
  function hide(){state(false)}
  button.hidden=false;
  button.addEventListener('click',function(){state(field.type==='password');field.focus()});
  field.form.addEventListener('submit',hide);
  window.addEventListener('pagehide',hide);
  document.addEventListener('visibilitychange',function(){if(document.hidden){hide()}});
})();
</script>
