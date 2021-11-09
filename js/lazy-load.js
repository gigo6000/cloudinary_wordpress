!function(t){var e={};function i(r){if(e[r])return e[r].exports;var n=e[r]={i:r,l:!1,exports:{}};return t[r].call(n.exports,n,n.exports,i),n.l=!0,n.exports}i.m=t,i.c=e,i.d=function(t,e,r){i.o(t,e)||Object.defineProperty(t,e,{enumerable:!0,get:r})},i.r=function(t){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(t,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(t,"__esModule",{value:!0})},i.t=function(t,e){if(1&e&&(t=i(t)),8&e)return t;if(4&e&&"object"==typeof t&&t&&t.__esModule)return t;var r=Object.create(null);if(i.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:t}),2&e&&"string"!=typeof t)for(var n in t)i.d(r,n,function(e){return t[e]}.bind(null,n));return r},i.n=function(t){var e=t&&t.__esModule?function(){return t.default}:function(){return t};return i.d(e,"a",e),e},i.o=function(t,e){return Object.prototype.hasOwnProperty.call(t,e)},i.p="",i(i.s=2)}([function(t,e,i){var r=i(3),n=i(4),o=i(5),s=i(6);t.exports=function(t){return r(t)||n(t)||o(t)||s()},t.exports.default=t.exports,t.exports.__esModule=!0},function(t,e){t.exports=function(t,e){(null==e||e>t.length)&&(e=t.length);for(var i=0,r=new Array(e);i<e;i++)r[i]=t[i];return r},t.exports.default=t.exports,t.exports.__esModule=!0},function(t,e,i){"use strict";i.r(e);var r=i(0),n=i.n(r),o={deviceDensity:window.devicePixelRatio?window.devicePixelRatio:"auto",density:null,images:[],throttle:!1,config:CLDLB||{},lazyThreshold:0,_init:function(){var t=this;this._calcThreshold();var e=document.querySelectorAll("noscript[data-image]");n()(e).forEach((function(e){var i=JSON.parse(e.dataset.image),r=document.createElement("img");for(var n in i)r.setAttribute(n,i[n]);r.originalWidth=i["data-size"][0],r.originalHeight=i["data-size"][1],t.images.push(r),e.parentNode.replaceChild(r,e)})),n()(document.images).forEach((function(e){if(e.dataset.publicId){var i=e.dataset.size.split(" ");e.originalWidth=i[0],e.originalHeight=i[1],i[2]&&(e.crop=i[2]),t.images.push(e),e.addEventListener("error",(function(i){e.src='data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="rgba(0,0,0,0.1)"/><text x="50%" y="50%" fill="red" text-anchor="middle" dominant-baseline="middle">%26%23x26A0%3B︎</text></svg>';var r=t.images.indexOf(e);t.images.splice(r,1)}))}})),window.addEventListener("resize",(function(){t._throttle(t._build.bind(t),100,!0)})),window.addEventListener("scroll",(function(){t._throttle(t._build.bind(t),100,!1)})),setTimeout((function(){return t._build()}),0)},_calcThreshold:function(){var t=this.config.lazy_threshold.replace(/[^0-9]/g,""),e=0;switch(this.config.lazy_threshold.replace(/[0-9]/g,"").toLowerCase()){case"em":e=parseFloat(getComputedStyle(document.body).fontSize)*t;break;case"rem":e=parseFloat(getComputedStyle(document.documentElement).fontSize)*t;break;case"vh":e=window.innerHeight/t*100;break;default:e=t}this.lazyThreshold=window.innerHeight+parseInt(e,10)},_getDensity:function(){if(this.density)return this.density;var t=this.config.dpr?this.config.dpr.replace("X",""):"off";if("off"===t)return this.density=1,1;var e=this.deviceDensity;return"max"!==t&&"auto"!==e&&(t=parseFloat(t),e=e>Math.ceil(t)?t:e),this.density=e,e},_throttle:function(t,e,i){var r=this;this.throttle||setTimeout((function(){t(i),r.throttle=!1}),e)},_build:function(t){var e=this;this.images.forEach((function(i){!t&&i.cld_loaded||e.buildSize(i)}))},_shouldRebuild:function(t){var e=this.scaleSize(t.originalWidth,t.width,this.config.pixel_step),i=t.getBoundingClientRect(),r="auto"!==this.density?this._getDensity():1;return i.top<this.lazyThreshold&&(e>t.naturalWidth/r||!t.cld_loaded)},_shouldPlacehold:function(t){var e=this.scaleSize(t.originalWidth,t.width,this.config.pixel_step),i=t.getBoundingClientRect(),r="auto"!==this.density?this._getDensity():1;return this.config.placeholder&&!t.cld_loaded&&i.top<2*this.lazyThreshold&&(e>t.naturalWidth/r||!t.cld_placehold)},getResponsiveSteps:function(t){return Math.ceil(t.dataset.breakpoints?t.originalWidth/t.dataset.breakpoints:this.responsiveStep)},scaleSize:function(t,e,i){var r=t-i*Math.floor((t-e)/i);return r>t?r=t:this.config.max_width<r?r=this.config.max_width:this.config.min_width>r&&(r=this.config.min_width),r},buildSize:function(t){this._shouldRebuild(t)?t.dataset.srcset?(t.cld_loaded=!0,t.srcset=t.dataset.srcset):t.src=this.getSizeURL(t):this._shouldPlacehold(t)&&(t.src=this.getPlaceholderURL(t))},getSizeURL:function(t){if(t.cld_loaded=!0,t.dataset.srcset)return t.srcset=t.dataset.srcset,delete t.dataset.srcset,"";var e=this.scaleSize(t.originalWidth,t.width,this.config.pixel_step),i=t.originalWidth/t.originalHeight,r=Math.round(e/i),n=this._getDensity(),o="auto"!==this.config.image_format&&"none"!==this.config.image_format?this.config.image_format:t.dataset.format,s=t.dataset.publicId.split("/").pop(),a=[];return e&&(t.crop&&a.push(t.crop),a.push("w_"+e),r&&(a.push("h_"+r),s+="-".concat(e,"x").concat(r)),1!==n&&a.push("dpr_"+n)),[this.config.base_url,"images",a.join(","),t.dataset.transformations,t.dataset.publicId,s+"."+o+"?_i=AA"].filter(this.empty).join("/")},getPlaceholderURL:function(t){t.cld_placehold=!0;var e=this.scaleSize(t.originalWidth,t.width,this.config.pixel_step),i=t.originalWidth/t.originalHeight,r=Math.round(e/i),n=[];return e&&(t.crop&&n.push(t.crop),n.push("w_"+e),r&&n.push("h_"+r)),[this.config.base_url,"images",n.join(","),this.config.placeholder,t.dataset.publicId,"responsive"].filter(this.empty).join("/")},empty:function(t){return 0!==t.length}};window.addEventListener("load",(function(){o._init()}))},function(t,e,i){var r=i(1);t.exports=function(t){if(Array.isArray(t))return r(t)},t.exports.default=t.exports,t.exports.__esModule=!0},function(t,e){t.exports=function(t){if("undefined"!=typeof Symbol&&null!=t[Symbol.iterator]||null!=t["@@iterator"])return Array.from(t)},t.exports.default=t.exports,t.exports.__esModule=!0},function(t,e,i){var r=i(1);t.exports=function(t,e){if(t){if("string"==typeof t)return r(t,e);var i=Object.prototype.toString.call(t).slice(8,-1);return"Object"===i&&t.constructor&&(i=t.constructor.name),"Map"===i||"Set"===i?Array.from(t):"Arguments"===i||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(i)?r(t,e):void 0}},t.exports.default=t.exports,t.exports.__esModule=!0},function(t,e){t.exports=function(){throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")},t.exports.default=t.exports,t.exports.__esModule=!0}]);