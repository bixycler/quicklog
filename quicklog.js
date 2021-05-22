

let md = window.markdownit({
  html: true,
  linkify: true,
  typographer: true,
});

function updateMD(txt, out){
  out.innerHTML = md.render(txt);
}
