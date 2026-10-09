function toggleMenu(){
  document.getElementById('sidebar')?.classList.toggle('open');
}
function openModal(id){
  const el=document.getElementById(id); if(!el)return;
  el.classList.add('show');
  document.body.classList.add('modal-open');
  const focus=el.querySelector('input:not([type=hidden]),select,textarea');
  setTimeout(()=>focus?.focus(),80);
}
function closeModal(id){
  const el=document.getElementById(id); if(!el)return;
  el.classList.remove('show');
  document.body.classList.remove('modal-open');
}
document.addEventListener('click',e=>{
  if(e.target.classList.contains('modal')) closeModal(e.target.id);
});
document.addEventListener('keydown',e=>{
  if(e.key==='Escape') document.querySelectorAll('.modal.show').forEach(m=>closeModal(m.id));
});
function updateClock(){
  const el=document.getElementById('clock'); if(!el)return;
  el.textContent=new Intl.DateTimeFormat('es-CO',{dateStyle:'short',timeStyle:'short'}).format(new Date());
}
updateClock(); setInterval(updateClock,30000);
