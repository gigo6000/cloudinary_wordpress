!function(t){var e={};function n(o){if(e[o])return e[o].exports;var r=e[o]={i:o,l:!1,exports:{}};return t[o].call(r.exports,r,r.exports,n),r.l=!0,r.exports}n.m=t,n.c=e,n.d=function(t,e,o){n.o(t,e)||Object.defineProperty(t,e,{enumerable:!0,get:o})},n.r=function(t){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(t,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(t,"__esModule",{value:!0})},n.t=function(t,e){if(1&e&&(t=n(t)),8&e)return t;if(4&e&&"object"==typeof t&&t&&t.__esModule)return t;var o=Object.create(null);if(n.r(o),Object.defineProperty(o,"default",{enumerable:!0,value:t}),2&e&&"string"!=typeof t)for(var r in t)n.d(o,r,function(e){return t[e]}.bind(null,r));return o},n.n=function(t){var e=t&&t.__esModule?function(){return t.default}:function(){return t};return n.d(e,"a",e),e},n.o=function(t,e){return Object.prototype.hasOwnProperty.call(t,e)},n.p="",n(n.s=2)}([function(t,e,n){var o=n(3),r=n(4),i=n(5),a=n(6);t.exports=function(t){return o(t)||r(t)||i(t)||a()},t.exports.default=t.exports,t.exports.__esModule=!0},function(t,e){t.exports=function(t,e){(null==e||e>t.length)&&(e=t.length);for(var n=0,o=new Array(e);n<e;n++)o[n]=t[n];return o},t.exports.default=t.exports,t.exports.__esModule=!0},function(t,e,n){"use strict";n.r(e);var o=n(0),r=n.n(o),i={modal:document.getElementById("cloudinary-deactivation"),modalBody:document.getElementById("modal-body"),modalFooter:document.getElementById("modal-footer"),modalUninstall:document.getElementById("modal-uninstall"),modalClose:document.getElementsByClassName("cancel-close"),pluginListLinks:document.querySelectorAll(".cld-deactivate-link, .cld-deactivate"),triggers:document.getElementsByClassName("cld-deactivate"),options:document.querySelectorAll('.cloudinary-deactivation .reasons input[type="radio"]'),report:document.getElementById("cld-report"),contact:document.getElementById("cld-contact"),submitButton:document.querySelector(".cloudinary-deactivation .button-primary"),reason:"",more:null,deactivationUrl:"",addEvents:function(){var t=this;r()(t.modalClose).forEach((function(e){e.addEventListener("click",(function(e){t.closeModal()}))})),window.addEventListener("keyup",(function(e){"visible"===t.modal.style.visibility&&"Escape"===e.key&&(t.modal.style.visibility="hidden",t.modal.style.opacity="0")})),t.modal.addEventListener("click",(function(e){e.stopPropagation(),e.target===t.modal&&t.closeModal()})),r()(t.pluginListLinks).forEach((function(e){e.addEventListener("click",(function(e){e.preventDefault(),t.deactivationUrl=e.target.getAttribute("href"),t.openModal()}))})),r()(t.triggers).forEach((function(t){t.addEventListener("click",(function(t){confirm(wp.i18n.__('Caution: Your storage setting is currently set to "Cloudinary only", disabling the plugin will result in broken links to media assets. Are you sure you want to continue?',"cloudinary"))||(t.preventDefault(),document.getElementById("TB_closeWindowButton").click())}))})),r()(t.options).forEach((function(e){e.addEventListener("change",(function(e){t.submitButton.removeAttribute("disabled"),t.reason=e.target.value,t.more=e.target.parentNode.querySelector("textarea")}))})),t.contact&&t.report.addEventListener("change",(function(){t.report.checked?t.contact.parentNode.removeAttribute("style"):t.contact.parentNode.style.display="none"})),t.submitButton.addEventListener("click",(function(){var e=document.querySelector('.cloudinary-deactivation .data input[name="option"]:checked');e&&"uninstall"===e.value?(t.modalBody.style.display="none",t.modalFooter.style.display="none",t.modalUninstall.style.display="block",t.uninstall()):t.submit(e.value)}))},closeModal:function(){this.modal.style.visibility="hidden",this.modal.style.opacity="0"},openModal:function(){this.modal.style.visibility="visible",this.modal.style.opacity="1"},uninstall:function(){},submit:function(){var t,e=arguments.length>0&&void 0!==arguments[0]?arguments[0]:"";wp.ajax.send({url:CLD_Deactivate.endpoint,data:{reason:this.reason,more:null===(t=this.more)||void 0===t?void 0:t.value,report:this.report.checked,contact:this.contact.checked,dataHandling:e},beforeSend:function(t){t.setRequestHeader("X-WP-Nonce",CLD_Deactivate.nonce)}}).always((function(){}))},init:function(){this.addEvents()}};i.init(),e.default=i},function(t,e,n){var o=n(1);t.exports=function(t){if(Array.isArray(t))return o(t)},t.exports.default=t.exports,t.exports.__esModule=!0},function(t,e){t.exports=function(t){if("undefined"!=typeof Symbol&&null!=t[Symbol.iterator]||null!=t["@@iterator"])return Array.from(t)},t.exports.default=t.exports,t.exports.__esModule=!0},function(t,e,n){var o=n(1);t.exports=function(t,e){if(t){if("string"==typeof t)return o(t,e);var n=Object.prototype.toString.call(t).slice(8,-1);return"Object"===n&&t.constructor&&(n=t.constructor.name),"Map"===n||"Set"===n?Array.from(t):"Arguments"===n||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?o(t,e):void 0}},t.exports.default=t.exports,t.exports.__esModule=!0},function(t,e){t.exports=function(){throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")},t.exports.default=t.exports,t.exports.__esModule=!0}]);