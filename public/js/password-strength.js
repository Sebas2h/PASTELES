document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('[data-password-meter]').forEach(input=>{
  const box=input.closest('[data-password-box]');if(!box)return;
  const bar=box.querySelector('.strength i');const track=box.querySelector('.strength');const caption=document.createElement('div');caption.className='password-meter-caption';caption.innerHTML='<span>Seguridad de la contraseña</span><strong>Sin contraseña</strong>';track.before(caption);const levelText=caption.querySelector('strong');const rules=[['length',v=>v.length>=8],['upper',v=>/[A-Z]/.test(v)],['lower',v=>/[a-z]/.test(v)],['number',v=>/\d/.test(v)],['symbol',v=>/[^A-Za-z0-9]/.test(v)]];
  const update=()=>{const value=input.value;let score=0;rules.forEach(([name,test])=>{const met=test(value);score+=met?1:0;box.querySelector(`[data-rule="${name}"]`)?.classList.toggle('met',met);});const level=score===5?'strong':score>=3?'medium':'weak';bar.style.width=(score*20)+'%';bar.dataset.level=level;levelText.dataset.level=level;levelText.textContent=value===''?'Sin contraseña':level==='strong'?'Fuerte':level==='medium'?'Media':'Débil';};
  input.addEventListener('input',update);update();
 });
});
