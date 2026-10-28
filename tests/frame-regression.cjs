/* Node regression: exercise native frame hooks and both import outcomes. */
const assert = require('assert');
const fs = require('fs');
const vm = require('vm');
const path = require('path');
const code = fs.readFileSync(path.join(__dirname, '..', 'assets/js/featured-image-from-url.js'), 'utf8');
const config = {
 ajax:'/admin-ajax.php',nonce:'nonce',postId:27,tab:'From URL',label:'Image URL',ph:'https://example.org/a.png',
 btn:'Fetch image',featuredBtn:'Set featured image',fetch:'Importing',ok:'Done',err:'Error',libraryBtn:'Import from URL',libraryTitle:'Import'
};
const events = { };
function Dom() { this.value='https://example.org/photo.png'; this.props={}; this.visible=true;this.length=1; }
Dom.prototype.css=Dom.prototype.empty=Dom.prototype.append=Dom.prototype.trigger=Dom.prototype.insertAfter=Dom.prototype.on=Dom.prototype.html=function(){return this};
Dom.prototype.val=function(){return this.value};
Dom.prototype.prop=function(k,v){this.props[k]=v;return this};
Dom.prototype.text=function(){return this};
Dom.prototype.first=function(){return this};
Dom.prototype.hasClass=function(){return false};
Dom.prototype.find=function(){return new Dom()};
function $(arg) {if(typeof arg==='function'){return new Dom()}return new Dom()}
$.trim=s=>String(s).trim();
let request=null, shouldFail=false, editedFeatured=null;
$.post=(url,values)=>{
  request={url,values};
  const response={success:!shouldFail,data:shouldFail?{message:'failed'}:{id:700,attachment:{id:700,mime:'image/png'},html:'<img src="x">'}};
  return {done(fn){fn(response);return this},fail(){return this},always(fn){fn();return this}};
};
function Klass(){}
Klass.prototype.initialize=function(){this.initialized=true;};
let selected=[],library=[],returnedMode='',closeCount=0;
let model={set(v){this.attrs=v}};
const media={
 view:{MediaFrame:{Select:Klass,Post:Klass,Manage:Klass}},
 View:{extend(proto){function View(){this.$el=new Dom()}Object.assign(View.prototype,proto);View.prototype.$=function(){return new Dom()};return View}},
 model:{Attachment:{get(id){assert.strictEqual(id,700);return model}}},
 featuredImage:{frame(){return currentFrame}},
 data:{dispatch(){return {editPost(v){editedFeatured=v}}}},
};
let currentFrame;
media.data=undefined;
const wp={media,data:{dispatch(){return {editPost(v){editedFeatured=v}}}}};
const sandbox={window:{wp},wp,jQuery:$,FIFU:config,document:{},setTimeout,console};
vm.runInNewContext(code,sandbox);
function makeFrame(stateId) {
  const frame={_events:{},on(name,callback){this._events[name]=callback},
    state(){return {id:stateId,get(n){if(n==='library')return {add(x){library.push(x)}};if(n==='selection')return {reset(x){selected=x}};return null}}},
    content:{set(v){this.view=v},mode(v){returnedMode=v}},
    router:{navigate(v){this.route=v}},
    close(){closeCount++}
  };
  currentFrame=frame;
  Klass.prototype.initialize.call(frame);
  return frame;
}
let frame=makeFrame('insert');
assert.strictEqual(frame._fifuPatched,true,'selector wrapped');
let tab={};frame._events['router:render:browse']({set(obj){tab=obj}});
assert.strictEqual(tab.fromURL.text,'From URL','From URL tab registered');
frame._events['content:render:fromURL']();
assert.ok(frame.content.view,'panel created');
frame.content.view.render();
frame.content.view.fetchImage({preventDefault(){}});
assert.strictEqual(request.values.mode,'library','general mode is distinct');
assert.strictEqual(selected.length,1,'newly imported Media Library attachment selected');
assert.strictEqual(library.length,1,'attachment added to library collection');
assert.strictEqual(returnedMode,'browse','returned to native media grid');
assert.strictEqual(closeCount,0,'general media picker not closed');
console.log('PASS general Media Library import and selection');
frame=makeFrame('featured-image');
frame._events['content:render:fromURL']();
frame.content.view.render();frame.content.view.fetchImage({preventDefault(){}});
assert.strictEqual(request.values.mode,'featured','featured image request mode');
assert.strictEqual(closeCount,1,'featured modal closed after import');
assert.strictEqual(editedFeatured.featured_media,700,'Gutenberg featured image updated');
console.log('PASS existing Featured Image behavior retained');
assert.ok(code.includes("'Select', 'Post', 'Manage'"),'all WordPress media frame types hooked');
assert.ok(code.includes('fifu-library-launch'),'Media Library page import button exists');
console.log('PASS Select/Post/Manage and Media Library page integration points');
