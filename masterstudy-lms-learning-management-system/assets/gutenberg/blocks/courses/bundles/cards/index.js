(()=>{var e,t={7853:(e,t,n)=>{"use strict";const r=window.React,s=window.wp.i18n,a=window.wp.blocks,l=window.wp.element;let i=function(e){return e.NORMAL="Normal",e.HOVER="Hover",e.ACTIVE="Active",e.FOCUS="Focus",e}({}),o=function(e){return e.DESKTOP="Desktop",e.TABLET="Tablet",e.MOBILE="Mobile",e}({}),c=function(e){return e.TOP_lEFT="top-left",e.TOP_CENTER="top-center",e.TOP_RIGHT="top-right",e.BOTTOM_lEFT="bottom-left",e.BOTTOM_CENTER="bottom-center",e.BOTTOM_RIGHT="bottom-right",e}({});s.__("Small","masterstudy-lms-learning-management-system"),s.__("Normal","masterstudy-lms-learning-management-system"),s.__("Large","masterstudy-lms-learning-management-system"),s.__("Extra Large","masterstudy-lms-learning-management-system");const m="wp-block-masterstudy-settings__";function d(e){return Array.isArray(e)?e.map((e=>m+e)):m+e}c.TOP_lEFT,c.TOP_CENTER,c.TOP_RIGHT,c.BOTTOM_lEFT,c.BOTTOM_CENTER,c.BOTTOM_RIGHT,s.__("Newest","masterstudy-lms-learning-management-system"),s.__("Oldest","masterstudy-lms-learning-management-system"),s.__("Overall rating","masterstudy-lms-learning-management-system"),s.__("Popular","masterstudy-lms-learning-management-system"),s.__("Price low","masterstudy-lms-learning-management-system"),s.__("Price high","masterstudy-lms-learning-management-system");const u=window.wp.apiFetch;var _=n.n(u);var h=n(6942),p=n.n(h);const g=window.wp.components,v=({condition:e,fallback:t=null,children:n})=>(0,r.createElement)(r.Fragment,null,e?n:t),C=window.wp.data,b=(0,l.createContext)(null),f=e=>(0,r.createElement)(g.SVG,{width:"16",height:"16",viewBox:"0 0 14 15",fill:"none",xmlns:"http://www.w3.org/2000/svg",...e},(0,r.createElement)(g.G,{"clip-path":"url(#clip0_1068_38993)"},(0,r.createElement)(g.Path,{d:"M11.1973 8.60005L8.74967 8.11005V5.67171C8.74967 5.517 8.68822 5.36863 8.57882 5.25923C8.46942 5.14984 8.32105 5.08838 8.16634 5.08838H6.99967C6.84496 5.08838 6.69659 5.14984 6.5872 5.25923C6.4778 5.36863 6.41634 5.517 6.41634 5.67171V8.58838H5.24967C5.15021 8.58844 5.05241 8.61391 4.96555 8.66238C4.87869 8.71084 4.80565 8.78068 4.75336 8.86529C4.70106 8.9499 4.67125 9.04646 4.66674 9.14582C4.66223 9.24518 4.68317 9.34405 4.72759 9.43305L6.47759 12.933C6.57676 13.1302 6.77859 13.255 6.99967 13.255H11.083C11.2377 13.255 11.3861 13.1936 11.4955 13.0842C11.6049 12.9748 11.6663 12.8264 11.6663 12.6717V9.17171C11.6665 9.03685 11.6198 8.90612 11.5343 8.80186C11.4487 8.69759 11.3296 8.62626 11.1973 8.60005Z"}),(0,r.createElement)(g.Path,{d:"M10.4997 1.58838H3.49967C2.85626 1.58838 2.33301 2.11221 2.33301 2.75505V6.83838C2.33301 7.4818 2.85626 8.00505 3.49967 8.00505H5.24967V6.83838H3.49967V2.75505H10.4997V6.83838H11.6663V2.75505C11.6663 2.11221 11.1431 1.58838 10.4997 1.58838Z"})),(0,r.createElement)("defs",null,(0,r.createElement)("clipPath",{id:"clip0_1068_38993"},(0,r.createElement)(g.Rect,{width:"14",height:"14",transform:"translate(0 0.421875)"})))),[y,w,E,L,O]=(i.NORMAL,s.__("Normal State","masterstudy-lms-learning-management-system"),i.HOVER,s.__("Hovered State","masterstudy-lms-learning-management-system"),i.ACTIVE,s.__("Hovered State","masterstudy-lms-learning-management-system"),i.FOCUS,s.__("Hovered State","masterstudy-lms-learning-management-system"),i.NORMAL,(0,r.createElement)((e=>(0,r.createElement)(g.SVG,{width:"16",height:"16",viewBox:"0 0 12 13",fill:"none",xmlns:"http://www.w3.org/2000/svg",...e},(0,r.createElement)(g.Path,{d:"M5.053 12.422a.63.63 0 0 1-.584-.391L.05 1.294A.632.632 0 0 1 .871.469L11.61 4.89a.633.633 0 0 1-.088 1.198l-4.685 1.17-1.17 4.686a.63.63 0 0 1-.614.478M1.793 2.214l3.113 7.56.797-3.19a.63.63 0 0 1 .46-.46l3.19-.797z"}))),null),s.__("Normal State","masterstudy-lms-learning-management-system"),i.HOVER,(0,r.createElement)(f,null),s.__("Hovered State","masterstudy-lms-learning-management-system"),i.ACTIVE,(0,r.createElement)(f,null),s.__("Active State","masterstudy-lms-learning-management-system"),i.FOCUS,(0,r.createElement)(f,null),s.__("Focus State","masterstudy-lms-learning-management-system"),d(["hover-state","hover-state__selected","hover-state__selected__opened-menu","hover-state__menu","hover-state__menu__item"])),S=window.wp.blockEditor,T=d("color-indicator");var k;function N(){return N=Object.assign?Object.assign.bind():function(e){for(var t=1;t<arguments.length;t++){var n=arguments[t];for(var r in n)({}).hasOwnProperty.call(n,r)&&(e[r]=n[r])}return e},N.apply(null,arguments)}(0,l.memo)((({color:e,onChange:t})=>(0,r.createElement)("div",{className:T},(0,r.createElement)(S.PanelColorSettings,{enableAlpha:!0,disableCustomColors:!1,__experimentalHasMultipleOrigins:!0,__experimentalIsRenderedInSidebar:!0,colorSettings:[{label:"",value:e,onChange:t}]}))));var M,x,D=function(e){return r.createElement("svg",N({xmlns:"http://www.w3.org/2000/svg",width:16,height:16,fill:"none",viewBox:"0 0 12 13"},e),k||(k=r.createElement("path",{d:"M5.053 12.422a.63.63 0 0 1-.584-.391L.05 1.294A.632.632 0 0 1 .871.469L11.61 4.89a.633.633 0 0 1-.088 1.198l-4.685 1.17-1.17 4.686a.63.63 0 0 1-.614.478M1.793 2.214l3.113 7.56.797-3.19a.63.63 0 0 1 .46-.46l3.19-.797z"})))};function P(){return P=Object.assign?Object.assign.bind():function(e){for(var t=1;t<arguments.length;t++){var n=arguments[t];for(var r in n)({}).hasOwnProperty.call(n,r)&&(e[r]=n[r])}return e},P.apply(null,arguments)}var A=function(e){return r.createElement("svg",P({xmlns:"http://www.w3.org/2000/svg",width:16,height:16,fill:"none",viewBox:"0 0 14 15"},e),M||(M=r.createElement("g",{clipPath:"url(#state-hover_svg__a)"},r.createElement("path",{d:"M11.197 8.6 8.75 8.11V5.672a.583.583 0 0 0-.584-.584H7a.583.583 0 0 0-.584.584v2.916H5.25a.584.584 0 0 0-.522.845l1.75 3.5c.099.197.3.322.522.322h4.083a.583.583 0 0 0 .583-.583v-3.5a.58.58 0 0 0-.469-.572"}),r.createElement("path",{d:"M10.5 1.588h-7c-.644 0-1.167.524-1.167 1.167v4.083c0 .644.523 1.167 1.167 1.167h1.75V6.838H3.5V2.755h7v4.083h1.166V2.755c0-.643-.523-1.167-1.166-1.167"}))),x||(x=r.createElement("defs",null,r.createElement("clipPath",{id:"state-hover_svg__a"},r.createElement("path",{d:"M0 .422h14v14H0z"})))))};const R=[{value:i.NORMAL,label:s.__("Normal State","masterstudy-lms-learning-management-system"),icon:e=>(0,r.createElement)(D,{onClick:e})},{value:i.HOVER,label:s.__("Hovered State","masterstudy-lms-learning-management-system"),icon:e=>(0,r.createElement)(A,{onClick:e})}],I={[i.NORMAL]:{icon:(0,r.createElement)(D,null),label:s.__("Normal State","masterstudy-lms-learning-management-system")},[i.HOVER]:{icon:(0,r.createElement)(A,null),label:s.__("Hovered State","masterstudy-lms-learning-management-system")}},V=d("hover-state"),H=d("hover-state__selected"),B=d("hover-state__selected__opened-menu"),F=d("has-changes"),j=d("hover-state__menu"),z=d("hover-state__menu__item"),[Z,U]=((0,l.memo)((e=>{const{hoverName:t,onChangeHoverName:n,fieldName:s}=e,{changedFieldsByName:a}=(()=>{const e=(0,l.useContext)(b);if(!e)throw new Error("No settings context provided");return e})(),i=a.get(s),{isOpen:o,onOpen:c,onClose:m}=((e=!1)=>{const[t,n]=(0,l.useState)(e),r=(0,l.useCallback)((()=>{n(!0)}),[]);return{isOpen:t,onClose:(0,l.useCallback)((()=>{n(!1)}),[]),onOpen:r,onToggle:(0,l.useCallback)((()=>{n((e=>!e))}),[])}})(),d=(e=>{const t=(0,l.useRef)(null);return(0,l.useEffect)((()=>{const n=n=>{t.current&&!t.current.contains(n.target)&&e()};return document.addEventListener("click",n),()=>{document.removeEventListener("click",n)}}),[t,e]),t})(m),{ICONS_MAP:u,options:_}=(e=>{const t=(0,l.useMemo)((()=>R.filter((t=>t.value!==e))),[e]);return{ICONS_MAP:I,options:t}})(t),h=(0,l.useCallback)((e=>{n(e),m()}),[n,m]);return(0,r.createElement)("div",{className:V,ref:d},(0,r.createElement)("div",{className:p()([H],{[B]:o,[F]:i}),onClick:c,title:u[t]?.label},u[t]?.icon),(0,r.createElement)(v,{condition:o},(0,r.createElement)("div",{className:j},_.map((({value:e,icon:t,label:n})=>(0,r.createElement)("div",{key:e,className:z,title:n},t((()=>h(e)))))))))})),s.__("Desktop","masterstudy-lms-learning-management-system"),s.__("Tablet","masterstudy-lms-learning-management-system"),s.__("Mobile","masterstudy-lms-learning-management-system"),o.DESKTOP,s.__("Desktop","masterstudy-lms-learning-management-system"),o.TABLET,s.__("Tablet","masterstudy-lms-learning-management-system"),o.MOBILE,s.__("Mobile","masterstudy-lms-learning-management-system"),d("device-picker"),d("device-picker__selected"),d("device-picker__selected__opened-menu"),d("device-picker__menu"),d("device-picker__menu__item"),d("reset-button"),d("unit"),d("unit__single"),d("unit__list"),d("popover-modal"),d("popover-modal__close dashicon dashicons dashicons-no-alt"),d("setting-label"),d("setting-label__content"),d("suffix"),d("color-picker"),d("number-steppers"),d("indent-steppers"),d("indent-stepper-plus"),d("indent-stepper-minus"),d(["indents","indents-control"])),[G,K,$,W]=d(["toggle-group-wrapper","toggle-group","toggle-group__toggle","toggle-group__active-toggle"]),[X,Y,q,J]=d(["border-control","border-control-solid","border-control-dashed","border-control-dotted"]),Q=(d("border-radius"),d("border-radius-control"),d("box-shadow-preset"),d("presets")),ee=d("presets__item-wrapper"),te=d("presets__item-wrapper__preset"),ne=d("presets__item-wrapper__name");(0,l.memo)((e=>{const{presets:t,activePreset:n,onSelectPreset:s,PresetItem:a,detectIsActive:l,detectByIndex:i=!1}=e;return(0,r.createElement)("div",{className:Q},t.map((({name:e,...t},o)=>(0,r.createElement)("div",{key:o,className:p()([ee],{active:l(n,i?o:t)}),onClick:()=>s(t)},(0,r.createElement)("div",{className:te},(0,r.createElement)(a,{preset:t})),(0,r.createElement)("span",{className:ne},e)))))})),d("range-control"),d("switch"),d("box-shadow-settings"),d("box-shadow-presets-title"),d("input-field"),d("input-field-control"),d("number-field"),d("number-field-control"),d("select__single-item"),d("select__container"),d("select__container__multi-item"),d("select"),d("select__select-box"),d("select__placeholder"),d("select__select-box-multiple"),d("select__menu"),d("select__menu__options-container"),d("select__menu__item"),d("setting-select"),d("row-select"),d("row-select__label"),d("row-select__control"),d("typography-select"),d("typography-select-label"),d("typography"),d("file-upload"),d("file-upload__wrap"),d("file-upload__image"),d("file-upload__remove"),d("file-upload__replace"),(0,l.createContext)({activeTab:0,setActiveTab:()=>{}}),d("tab-list"),d("tab"),d("tab-active"),d("content"),d("tab-panel"),window.ReactDOM;"undefined"!=typeof window&&void 0!==window.document&&window.document.createElement;function re(e){const t=Object.prototype.toString.call(e);return"[object Window]"===t||"[object global]"===t}function se(e){return"nodeType"in e}function ae(e){var t,n;return e?re(e)?e:se(e)&&null!=(t=null==(n=e.ownerDocument)?void 0:n.defaultView)?t:window:window}function le(e){const{Document:t}=ae(e);return e instanceof t}function ie(e){return!re(e)&&e instanceof ae(e).HTMLElement}function oe(e){return e instanceof ae(e).SVGElement}function ce(e){return e?re(e)?e.document:se(e)?le(e)?e:ie(e)||oe(e)?e.ownerDocument:document:document:document}function me(e){return function(t){for(var n=arguments.length,r=new Array(n>1?n-1:0),s=1;s<n;s++)r[s-1]=arguments[s];return r.reduce(((t,n)=>{const r=Object.entries(n);for(const[n,s]of r){const r=t[n];null!=r&&(t[n]=r+e*s)}return t}),{...t})}}const de=me(-1);function ue(e){if(function(e){if(!e)return!1;const{TouchEvent:t}=ae(e.target);return t&&e instanceof t}(e)){if(e.touches&&e.touches.length){const{clientX:t,clientY:n}=e.touches[0];return{x:t,y:n}}if(e.changedTouches&&e.changedTouches.length){const{clientX:t,clientY:n}=e.changedTouches[0];return{x:t,y:n}}}return function(e){return"clientX"in e&&"clientY"in e}(e)?{x:e.clientX,y:e.clientY}:null}var _e;!function(e){e.DragStart="dragStart",e.DragMove="dragMove",e.DragEnd="dragEnd",e.DragCancel="dragCancel",e.DragOver="dragOver",e.RegisterDroppable="registerDroppable",e.SetDroppableDisabled="setDroppableDisabled",e.UnregisterDroppable="unregisterDroppable"}(_e||(_e={}));const he=Object.freeze({x:0,y:0});var pe,ge,ve,Ce;!function(e){e[e.Forward=1]="Forward",e[e.Backward=-1]="Backward"}(pe||(pe={}));class be{constructor(e){this.target=void 0,this.listeners=[],this.removeAll=()=>{this.listeners.forEach((e=>{var t;return null==(t=this.target)?void 0:t.removeEventListener(...e)}))},this.target=e}add(e,t,n){var r;null==(r=this.target)||r.addEventListener(e,t,n),this.listeners.push([e,t,n])}}function fe(e,t){const n=Math.abs(e.x),r=Math.abs(e.y);return"number"==typeof t?Math.sqrt(n**2+r**2)>t:"x"in t&&"y"in t?n>t.x&&r>t.y:"x"in t?n>t.x:"y"in t&&r>t.y}function ye(e){e.preventDefault()}function we(e){e.stopPropagation()}!function(e){e.Click="click",e.DragStart="dragstart",e.Keydown="keydown",e.ContextMenu="contextmenu",e.Resize="resize",e.SelectionChange="selectionchange",e.VisibilityChange="visibilitychange"}(ge||(ge={})),(Ce=ve||(ve={})).Space="Space",Ce.Down="ArrowDown",Ce.Right="ArrowRight",Ce.Left="ArrowLeft",Ce.Up="ArrowUp",Ce.Esc="Escape",Ce.Enter="Enter";ve.Space,ve.Enter,ve.Esc,ve.Space,ve.Enter;function Ee(e){return Boolean(e&&"distance"in e)}function Le(e){return Boolean(e&&"delay"in e)}class Oe{constructor(e,t,n){var r;void 0===n&&(n=function(e){const{EventTarget:t}=ae(e);return e instanceof t?e:ce(e)}(e.event.target)),this.props=void 0,this.events=void 0,this.autoScrollEnabled=!0,this.document=void 0,this.activated=!1,this.initialCoordinates=void 0,this.timeoutId=null,this.listeners=void 0,this.documentListeners=void 0,this.windowListeners=void 0,this.props=e,this.events=t;const{event:s}=e,{target:a}=s;this.props=e,this.events=t,this.document=ce(a),this.documentListeners=new be(this.document),this.listeners=new be(n),this.windowListeners=new be(ae(a)),this.initialCoordinates=null!=(r=ue(s))?r:he,this.handleStart=this.handleStart.bind(this),this.handleMove=this.handleMove.bind(this),this.handleEnd=this.handleEnd.bind(this),this.handleCancel=this.handleCancel.bind(this),this.handleKeydown=this.handleKeydown.bind(this),this.removeTextSelection=this.removeTextSelection.bind(this),this.attach()}attach(){const{events:e,props:{options:{activationConstraint:t,bypassActivationConstraint:n}}}=this;if(this.listeners.add(e.move.name,this.handleMove,{passive:!1}),this.listeners.add(e.end.name,this.handleEnd),this.windowListeners.add(ge.Resize,this.handleCancel),this.windowListeners.add(ge.DragStart,ye),this.windowListeners.add(ge.VisibilityChange,this.handleCancel),this.windowListeners.add(ge.ContextMenu,ye),this.documentListeners.add(ge.Keydown,this.handleKeydown),t){if(null!=n&&n({event:this.props.event,activeNode:this.props.activeNode,options:this.props.options}))return this.handleStart();if(Le(t))return void(this.timeoutId=setTimeout(this.handleStart,t.delay));if(Ee(t))return}this.handleStart()}detach(){this.listeners.removeAll(),this.windowListeners.removeAll(),setTimeout(this.documentListeners.removeAll,50),null!==this.timeoutId&&(clearTimeout(this.timeoutId),this.timeoutId=null)}handleStart(){const{initialCoordinates:e}=this,{onStart:t}=this.props;e&&(this.activated=!0,this.documentListeners.add(ge.Click,we,{capture:!0}),this.removeTextSelection(),this.documentListeners.add(ge.SelectionChange,this.removeTextSelection),t(e))}handleMove(e){var t;const{activated:n,initialCoordinates:r,props:s}=this,{onMove:a,options:{activationConstraint:l}}=s;if(!r)return;const i=null!=(t=ue(e))?t:he,o=de(r,i);if(!n&&l){if(Ee(l)){if(null!=l.tolerance&&fe(o,l.tolerance))return this.handleCancel();if(fe(o,l.distance))return this.handleStart()}return Le(l)&&fe(o,l.tolerance)?this.handleCancel():void 0}e.cancelable&&e.preventDefault(),a(i)}handleEnd(){const{onEnd:e}=this.props;this.detach(),e()}handleCancel(){const{onCancel:e}=this.props;this.detach(),e()}handleKeydown(e){e.code===ve.Esc&&this.handleCancel()}removeTextSelection(){var e;null==(e=this.document.getSelection())||e.removeAllRanges()}}const Se={move:{name:"pointermove"},end:{name:"pointerup"}};(class extends Oe{constructor(e){const{event:t}=e,n=ce(t.target);super(e,Se,n)}}).activators=[{eventName:"onPointerDown",handler:(e,t)=>{let{nativeEvent:n}=e,{onActivation:r}=t;return!(!n.isPrimary||0!==n.button||(null==r||r({event:n}),0))}}];const Te={move:{name:"mousemove"},end:{name:"mouseup"}};var ke;!function(e){e[e.RightClick=2]="RightClick"}(ke||(ke={})),class extends Oe{constructor(e){super(e,Te,ce(e.event.target))}}.activators=[{eventName:"onMouseDown",handler:(e,t)=>{let{nativeEvent:n}=e,{onActivation:r}=t;return n.button!==ke.RightClick&&(null==r||r({event:n}),!0)}}];const Ne={move:{name:"touchmove"},end:{name:"touchend"}};var Me,xe,De,Pe,Ae;(class extends Oe{constructor(e){super(e,Ne)}static setup(){return window.addEventListener(Ne.move.name,e,{capture:!1,passive:!1}),function(){window.removeEventListener(Ne.move.name,e)};function e(){}}}).activators=[{eventName:"onTouchStart",handler:(e,t)=>{let{nativeEvent:n}=e,{onActivation:r}=t;const{touches:s}=n;return!(s.length>1||(null==r||r({event:n}),0))}}],function(e){e[e.Pointer=0]="Pointer",e[e.DraggableRect=1]="DraggableRect"}(Me||(Me={})),function(e){e[e.TreeOrder=0]="TreeOrder",e[e.ReversedTreeOrder=1]="ReversedTreeOrder"}(xe||(xe={})),pe.Backward,pe.Forward,pe.Backward,pe.Forward,function(e){e[e.Always=0]="Always",e[e.BeforeDragging=1]="BeforeDragging",e[e.WhileDragging=2]="WhileDragging"}(De||(De={})),function(e){e.Optimized="optimized"}(Pe||(Pe={})),De.WhileDragging,Pe.Optimized,Map,function(e){e[e.Uninitialized=0]="Uninitialized",e[e.Initializing=1]="Initializing",e[e.Initialized=2]="Initialized"}(Ae||(Ae={})),ve.Down,ve.Right,ve.Up,ve.Left,s.__("Lectures","masterstudy-lms-learning-management-system"),s.__("Duration","masterstudy-lms-learning-management-system"),s.__("Views","masterstudy-lms-learning-management-system"),s.__("Level","masterstudy-lms-learning-management-system"),s.__("Members","masterstudy-lms-learning-management-system"),s.__("Empty","masterstudy-lms-learning-management-system"),d("sortable__item"),d("sortable__item__disabled"),d("sortable__item__content"),d("sortable__item__content__drag-item"),d("sortable__item__content__drag-item__disabled"),d("sortable__item__content__title"),d("sortable__item__control"),d("sortable__item__icon"),d("nested-sortable"),d("nested-sortable__item"),d("sortable"),d("accordion"),d("accordion__header"),d("accordion__header-flex"),d("accordion__content"),d("accordion__icon"),d("accordion__title"),d("accordion__title-disabled"),d("accordion__indicator"),d("accordion__controls"),d("accordion__controls-disabled"),d("preset-picker"),d("preset-picker__label"),d("preset-picker__remove"),d("preset-picker__presets-list"),d("preset-picker__presets-list__item"),d("preset-picker__presets-list__item__preset"),d("preset-picker__presets-list__item__preset-active");const Re=e=>(0,r.createElement)("div",{className:"lms-course-preloader"},(0,r.createElement)(v,{condition:"height"in e&&"width"in e,fallback:(0,r.createElement)(g.Spinner,null)},(0,r.createElement)(g.Spinner,{style:{...e}}))),Ie=({isFetching:e,error:t,children:n})=>(0,r.createElement)(r.Fragment,null,n,(0,r.createElement)(v,{condition:e},(0,r.createElement)(Re,{width:"80px",height:"80px"}))),Ve=({bundle:e})=>{const t=p()("lms-course-bundle__courses",{"with-scroll":e.bundleCourses.length>3},{"with-sm-scroll":4===e.bundleCourses.length},{"with-lg-scroll":e.bundleCourses.length>4});return(0,r.createElement)("div",{className:"lms-course-bundle__item"},(0,r.createElement)("div",{className:"lms-course-bundle__header"},(0,r.createElement)("a",{className:"lms-course-bundle__header-title",href:e.bundleInfo.url,onClick:e=>e.preventDefault()},e.bundleInfo.title),(0,r.createElement)("div",{className:"lms-course-bundle__header-count"},s.sprintf(/* translators: %d is replaced with the number of courses */
s.__("%d courses","masterstudy-lms-learning-management-system"),e.bundleCourses.length))),(0,r.createElement)("div",{className:"lms-course-bundle__body"},(0,r.createElement)("div",{className:"lms-course-bundle__body-popup"},(0,r.createElement)("div",{className:t},e.bundleCourses.map((e=>(0,r.createElement)(He,{key:e.id,course:e})))),(0,r.createElement)("div",{className:"lms-course-bundle__footer"},(0,r.createElement)("div",{className:"lms-course-bundle__bundle-rating"},(0,r.createElement)("span",{className:"lms-course-bundle__bundle-rating__progress"},(0,r.createElement)("span",{className:"lms-course-bundle__bundle-rating__progress--active",style:{width:20*e.bundleInfo.rating+"%"}})),(0,r.createElement)("span",{className:"lms-course-bundle__bundle-rating__count"},e.bundleInfo.rating," (",e.bundleInfo.reviews,")")),(0,r.createElement)("div",{className:"lms-course-bundle__bundle-price"},(0,r.createElement)("span",{className:"lms-course-bundle__bundle-price__regular"},e.bundleInfo.priceBundle),(0,r.createElement)("span",{className:"lms-course-bundle__bundle-price__old"},e.bundleInfo.priceCourses))))))},He=({course:e})=>(0,r.createElement)("div",{className:"lms-course-bundle__courses-item"},(0,r.createElement)("div",{className:"lms-course-bundle__courses-preview",dangerouslySetInnerHTML:{__html:e.cover}}),(0,r.createElement)("div",{className:"lms-course-bundle__courses-title"},(0,r.createElement)("a",{href:e.permalink,onClick:e=>e.preventDefault()},e.postTitle)),(0,r.createElement)("div",{className:"lms-course-bundle__courses-price"},(0,r.createElement)("span",{className:"lms-course-bundle__courses-price__regular"},e.salePrice||e.price),Boolean(e.salePrice)&&(0,r.createElement)("span",{className:"lms-course-bundle__courses-price__old"},e.price))),Be=JSON.parse('{"UU":"masterstudy/courses-bundles-cards"}');(0,a.registerBlockType)(Be.UU,{title:s._x("MasterStudy Courses Bundles","block title","masterstudy-lms-learning-management-system"),description:s._x("Set up how your course bundles will look on the page with this block","block description","masterstudy-lms-learning-management-system"),category:"masterstudy-lms-blocks",icon:{src:(0,r.createElement)("svg",{width:"512",height:"512",viewBox:"0 0 512 512",fill:"none",xmlns:"http://www.w3.org/2000/svg"},(0,r.createElement)("g",{clipPath:"url(#clip0_3050_88379)"},(0,r.createElement)("path",{opacity:"0.3",fillRule:"evenodd",clipRule:"evenodd",d:"M457.931 368.504H491.746C493.421 368.503 495.071 368.106 496.55 367.349C498.03 366.591 499.294 365.496 500.234 364.16C501.173 362.823 501.759 361.285 501.94 359.68C502.121 358.075 501.891 356.452 501.272 354.952L464.192 269.026C463.514 267.371 462.382 265.925 460.918 264.842C459.453 263.758 457.712 263.078 455.878 262.873L284.486 243.088C282.589 242.839 280.657 243.121 278.921 243.9C277.186 244.678 275.718 245.921 274.694 247.48C273.64 249.015 273.054 250.804 273.004 252.644C272.976 253.628 273.103 254.605 273.374 255.544C273.188 256.292 273.092 257.065 273.092 257.847V490.107C273.108 492.726 274.195 495.233 276.116 497.085C278.038 498.937 280.639 499.984 283.357 500C283.762 500.002 284.167 499.976 284.568 499.921L448.878 480.136C451.374 479.858 453.678 478.704 455.348 476.894C457.018 475.084 457.938 472.744 457.931 470.322V368.504Z",fill:"#227AFF"}),(0,r.createElement)("path",{d:"M255.722 512C255.301 512.003 254.88 511.975 254.463 511.915L73.2791 490.581C70.6845 490.282 68.2909 489.038 66.5555 487.086C64.8201 485.134 63.8645 482.612 63.8711 480V352C63.8711 349.171 64.9949 346.458 66.9953 344.458C68.9957 342.457 71.7088 341.333 74.5378 341.333C77.3668 341.333 80.0799 342.457 82.0803 344.458C84.0807 346.458 85.2045 349.171 85.2045 352V470.528L245.055 489.344V225.92C244.734 224.174 244.865 222.375 245.436 220.695C246.008 219.014 247 217.508 248.319 216.32C249.859 214.91 251.773 213.974 253.832 213.626C255.891 213.278 258.007 213.532 259.924 214.357C263.786 216.064 266.388 219.776 266.388 224V501.333C266.388 504.162 265.265 506.875 263.264 508.876C261.264 510.876 258.551 512 255.722 512Z",fill:"black"}),(0,r.createElement)("path",{d:"M256.151 512C253.327 511.983 250.624 510.854 248.627 508.857C246.63 506.86 245.501 504.157 245.484 501.333V229.333C245.484 226.504 246.608 223.791 248.609 221.791C250.609 219.79 253.322 218.667 256.151 218.667C258.98 218.667 261.693 219.79 263.694 221.791C265.694 223.791 266.818 226.504 266.818 229.333V489.344L426.647 470.528V352C426.647 349.171 427.771 346.458 429.771 344.457C431.772 342.457 434.485 341.333 437.314 341.333C440.143 341.333 442.856 342.457 444.856 344.457C446.857 346.458 447.98 349.171 447.98 352V480C447.987 482.612 447.031 485.134 445.296 487.086C443.561 489.038 441.167 490.282 438.572 490.581L257.41 511.915C256.993 511.974 256.572 512.003 256.151 512Z",fill:"black"}),(0,r.createElement)("path",{d:"M480 362.667H330.603C328.738 362.667 326.906 362.179 325.289 361.251C323.672 360.323 322.326 358.987 321.387 357.376L246.848 229.376C245.843 227.665 245.338 225.706 245.39 223.723C245.443 221.739 246.052 219.81 247.147 218.155C248.211 216.474 249.736 215.134 251.54 214.294C253.344 213.455 255.351 213.151 257.323 213.419L438.571 234.752C440.476 234.973 442.286 235.707 443.808 236.875C445.329 238.043 446.505 239.603 447.211 241.387L489.899 348.053C490.543 349.671 490.781 351.421 490.593 353.152C490.405 354.883 489.797 356.541 488.82 357.982C487.844 359.424 486.53 360.604 484.992 361.421C483.455 362.237 481.741 362.665 480 362.667ZM336.725 341.333H464.256L429.781 255.189L276.032 237.077L336.725 341.333Z",fill:"black"}),(0,r.createElement)("path",{d:"M181.396 362.667H31.9987C30.2533 362.672 28.5335 362.247 26.9908 361.431C25.4481 360.615 24.1297 359.431 23.1519 357.986C22.174 356.54 21.5666 354.876 21.3832 353.14C21.1997 351.405 21.4459 349.65 22.1 348.032L64.788 241.365C65.4952 239.583 66.6718 238.025 68.1929 236.856C69.7141 235.688 71.5231 234.954 73.428 234.731L254.676 213.397C256.642 213.172 258.632 213.498 260.424 214.337C262.217 215.176 263.741 216.497 264.827 218.151C265.913 219.805 266.518 221.729 266.575 223.707C266.632 225.685 266.139 227.64 265.151 229.355L190.612 357.355C189.675 358.969 188.331 360.309 186.713 361.242C185.096 362.174 183.263 362.665 181.396 362.667ZM47.7427 341.333H175.273L235.988 237.077L82.2387 255.189L47.7427 341.333Z",fill:"black"}),(0,r.createElement)("path",{d:"M303.211 112.931C272.872 131.005 263.35 178.205 262.956 180.208C262.248 183.848 264.622 187.373 268.262 188.085C268.695 188.17 269.128 188.209 269.554 188.209C272.702 188.209 275.515 185.986 276.138 182.779C276.224 182.349 284.848 139.505 310.084 124.467C313.271 122.568 314.314 118.446 312.418 115.262C310.516 112.078 306.398 111.032 303.211 112.931ZM343.353 45.1322C340.848 45.6405 338.848 47.5291 338.199 50.0015L333.097 69.3484L314.274 76.1129C311.867 76.9754 310.162 79.1296 309.874 81.6713C309.579 84.2093 310.759 86.6947 312.91 88.0786L329.725 98.9062L330.335 118.905C330.414 121.46 331.942 123.748 334.264 124.808C335.155 125.214 336.106 125.414 337.051 125.414C338.573 125.414 340.074 124.899 341.294 123.903L356.798 111.249L376.006 116.843C378.453 117.564 381.102 116.82 382.833 114.934C384.559 113.049 385.07 110.347 384.145 107.966L376.899 89.3145L388.159 72.7749C389.595 70.6635 389.707 67.9155 388.448 65.6921C387.195 63.4687 384.939 62.1833 382.224 62.2916L362.254 63.4197L350.003 47.6015C348.429 45.578 345.852 44.6307 343.353 45.1322ZM353.794 74.4343C355.152 76.1817 357.191 77.1754 359.487 77.028L369.396 76.4671L363.808 84.678C362.569 86.5046 362.3 88.826 363.1 90.8853L366.694 100.142L357.165 97.3681C355.053 96.7583 352.764 97.214 351.046 98.6109L343.347 104.894L343.045 94.9645C342.973 92.7546 341.825 90.7213 339.963 89.5244L331.62 84.1499L340.96 80.7921C343.039 80.0447 344.619 78.3234 345.183 76.1853L347.715 66.5838L353.794 74.4343ZM189.8 71.8994L172.361 69.9614L164.078 54.4942C162.872 52.2447 160.569 50.864 157.933 50.9525C155.376 51.0379 153.093 52.5661 152.037 54.8973L144.804 70.8864L127.529 73.9885C125.011 74.4405 122.972 76.2838 122.263 78.7395C121.555 81.1958 122.302 83.8421 124.191 85.5635L137.163 97.3812L134.782 114.767C134.435 117.299 135.55 119.807 137.668 121.24C138.803 122.007 140.115 122.394 141.432 122.394C142.574 122.394 143.722 122.103 144.758 121.516L160.005 112.833L175.804 120.476C178.106 121.591 180.841 121.299 182.854 119.725C184.874 118.155 185.825 115.574 185.307 113.072L181.759 95.8859L193.905 83.2223C195.676 81.376 196.246 78.6874 195.374 76.2838C194.502 73.8801 192.344 72.1817 189.8 71.8994ZM169.62 89.1343C168.092 90.7312 167.442 92.9775 167.889 95.1416L169.397 102.457L162.675 99.2046C160.688 98.2468 158.353 98.326 156.425 99.4145L149.939 103.11L150.949 95.7088C151.25 93.5218 150.457 91.3218 148.824 89.8327L143.302 84.8025L150.653 83.4812C152.831 83.0911 154.674 81.6515 155.585 79.6379L158.668 72.8338L162.189 79.415C163.239 81.3661 165.174 82.6744 167.37 82.9171L174.794 83.7437L169.62 89.1343ZM199.682 105.025C227.673 108.619 239.76 143.226 239.878 143.574C240.829 146.371 243.439 148.135 246.239 148.135C246.954 148.135 247.676 148.021 248.39 147.778C251.899 146.591 253.788 142.794 252.607 139.282C252.017 137.534 237.773 96.3713 201.388 91.7051C197.715 91.2197 194.351 93.8364 193.872 97.5093C193.4 101.188 196.003 104.553 199.682 105.025Z",fill:"black"})),(0,r.createElement)("defs",null,(0,r.createElement)("clipPath",{id:"clip0_3050_88379"},(0,r.createElement)("rect",{width:"512",height:"512",fill:"white"}))))},edit:({isSelected:e,context:t})=>{(e=>{const t=(0,C.useSelect)((e=>{const{getBlockParents:t,getSelectedBlockClientId:n}=e(S.store);return t(n(),!0)}),[]),{selectBlock:n}=(0,C.useDispatch)(S.store);(0,l.useEffect)((()=>{e&&t.length&&n(t[0])}),[e,t,n])})(e);const{path:n}=((e,t,n=[],r,s=[])=>{const[a,i]=(0,l.useState)("");return(0,l.useEffect)((()=>{(()=>{const a=[];e>0&&a.push(`per_page=${e}`),r&&a.push(`author=${r}`),t&&a.push(`sort=${t}`),n.length&&a.push(`category=${n.toString()}`),s.length&&a.push(`bundle_ids=${s.join(",")}`),i(a.join("&"))})()}),[r,e,t,n,s]),{path:a}})(t["masterstudy/bundlesPerPage"],t["masterstudy/bundlesOrderBy"],[],void 0,t["masterstudy/bundlesValues"]),{bundles:s,isFetching:a,error:i}=((e,t=!1)=>{const[n,r]=(0,l.useState)([]),{setIsFetching:s,setError:a,isFetching:i,error:o}=(()=>{const[e,t]=(0,l.useState)(!0),[n,r]=(0,l.useState)("");return{isFetching:e,setIsFetching:t,error:n,setError:r}})();return(0,l.useEffect)((()=>{(""!==e||t)&&(s(!0),(async e=>{try{return await _()({path:`masterstudy-lms/v2/course-bundles?${e}`})}catch(e){throw new Error(e)}})(e).then((e=>{r((e=>e.bundles.map((({bundle_info:e,bundle_courses:t})=>({bundleCourses:Object.values(t).map((e=>({id:e.id,cover:e.image,featured:"on"===e.is_featured,permalink:e.link,price:e.price,salePrice:e.sale_price,postTitle:e.title,views:String(e.views)}))),bundleInfo:{id:e.id,title:e.title,priceBundle:e.price,priceCourses:e.courses_price,url:e.url,rating:e.rating.count>0?Math.round(e.rating.average/e.rating.count*10)/10:0,reviews:e.rating.count}}))))(e))})).catch((e=>{a(e.message)})).finally((()=>{s(!1)})))}),[e,t]),{bundles:n,isFetching:i,error:o}})(n);return(0,r.createElement)(Ie,{isFetching:a,error:i},(0,r.createElement)("div",{className:"lms-course-bundle__list"},s.map((e=>(0,r.createElement)(Ve,{key:e.bundleInfo.id,bundle:e})))))},save:()=>(0,r.createElement)(r.Fragment,null,(0,r.createElement)("div",{className:"lms-course-bundle-preloader"},(0,r.createElement)("div",{className:"lms-course-bundle-preloader-item"})),(0,r.createElement)("div",{className:"lms-course-bundle__list"}))})},6942:(e,t)=>{var n;!function(){"use strict";var r={}.hasOwnProperty;function s(){for(var e="",t=0;t<arguments.length;t++){var n=arguments[t];n&&(e=l(e,a(n)))}return e}function a(e){if("string"==typeof e||"number"==typeof e)return e;if("object"!=typeof e)return"";if(Array.isArray(e))return s.apply(null,e);if(e.toString!==Object.prototype.toString&&!e.toString.toString().includes("[native code]"))return e.toString();var t="";for(var n in e)r.call(e,n)&&e[n]&&(t=l(t,n));return t}function l(e,t){return t?e?e+" "+t:e+t:e}e.exports?(s.default=s,e.exports=s):void 0===(n=function(){return s}.apply(t,[]))||(e.exports=n)}()}},n={};function r(e){var s=n[e];if(void 0!==s)return s.exports;var a=n[e]={exports:{}};return t[e](a,a.exports,r),a.exports}r.m=t,e=[],r.O=(t,n,s,a)=>{if(!n){var l=1/0;for(m=0;m<e.length;m++){for(var[n,s,a]=e[m],i=!0,o=0;o<n.length;o++)(!1&a||l>=a)&&Object.keys(r.O).every((e=>r.O[e](n[o])))?n.splice(o--,1):(i=!1,a<l&&(l=a));if(i){e.splice(m--,1);var c=s();void 0!==c&&(t=c)}}return t}a=a||0;for(var m=e.length;m>0&&e[m-1][2]>a;m--)e[m]=e[m-1];e[m]=[n,s,a]},r.n=e=>{var t=e&&e.__esModule?()=>e.default:()=>e;return r.d(t,{a:t}),t},r.d=(e,t)=>{for(var n in t)r.o(t,n)&&!r.o(e,n)&&Object.defineProperty(e,n,{enumerable:!0,get:t[n]})},r.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{var e={6632:0,2560:0};r.O.j=t=>0===e[t];var t=(t,n)=>{var s,a,[l,i,o]=n,c=0;if(l.some((t=>0!==e[t]))){for(s in i)r.o(i,s)&&(r.m[s]=i[s]);if(o)var m=o(r)}for(t&&t(n);c<l.length;c++)a=l[c],r.o(e,a)&&e[a]&&e[a][0](),e[a]=0;return r.O(m)},n=globalThis.webpackChunkmasterstudy_lms_learning_management_system=globalThis.webpackChunkmasterstudy_lms_learning_management_system||[];n.forEach(t.bind(null,0)),n.push=t.bind(null,n.push.bind(n))})();var s=r.O(void 0,[2560],(()=>r(7853)));s=r.O(s)})();