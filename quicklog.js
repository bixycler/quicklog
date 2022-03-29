'use strict';

fetch('log.json')
  .then(response => response.json())
  .then(obj => console.log(obj));

let md = window.markdownit({
  html: true,
  linkify: true,
  typographer: true,
});

function updateMD(txt, out){
  out.innerHTML = md.render(txt);
}

/*
 var words = encode_utf8('Marché')

// Original
function encode_utf8( s )
{
  return unescape( encodeURIComponent( s ) );
}

function decode_utf8( s )
{
  return decodeURIComponent( escape( s ) );
}
 */