const api_url = 'http://creatzy.epizy.com/quicklog/log.php';

function toDateString(t){
  return new Date(t).toDateString();
}
function toTimeString(t){
  if(t>0){ return new Date(t).toLocaleTimeString();
  }else{ return new Date(-t).toISOString().substr(11, 8); }
}

const markdown = window.markdownit({
  html: true,
  linkify: true,
  typographer: true,
});

let ts = 0;
let tss = toDateString(Date.now());
timestamp.innerHTML = tss;

logText.oninput = (e)=>{
  logHtml.innerHTML = markdown.render(logText.value);
  let now = Date.now();
  if(!ts){ ts = now;
    tss = toDateString(ts)+' &nbsp; '+toTimeString(ts);
  }
  timestamp.innerHTML = tss+' + '+toTimeString(-(now - ts));
}

 reqTime = 0;
function commitLog(log){
  let json = JSON.stringify(log);
  let cts = Date.now();
  fetch(api_url+'/entry/', {'method':'POST', 
    'headers':{'Content-Type':'application/json'}, 'body':json}
  ) .then(response => response.json())
    .then(data => {
      let dt = Date.now() - cts; reqTime += dt;
      console.log('Commit success (in '+dt+'ms):', data);
    }).catch((error) => {
      console.error('Commit error:', error);
    })
  ;
}
function commitLogText(){
  let now = Date.now();
  let log = {
    'in':logText.value,
    'ts':[ts, now],
  };
  commitLog(log);
  for(let i=0; i<1000; i++){ log.ts[0]=i; commitLog(log); } console.log('reqTime = '+reqTime);
  ts = 0; tss = toDateString(now);
  timestamp.innerHTML = tss;
  logHtml.innerHTML = ''; logText.value = '';
}

logText.onkeypress = (e)=>{
  if(e.ctrlKey && (e.keyCode===13 || e.keyCode===10)){ //Ctrl-Enter
    commitLogText();
  }
}


function updateBloggerLink(emailLink, editLink){
  let link = emailLink.replace('email-post.g?blogID=','blog/post/edit/').replace('&postID=','/');
  editLink.innerHTML = `<a href="${link}">${link}</a>`;
}
