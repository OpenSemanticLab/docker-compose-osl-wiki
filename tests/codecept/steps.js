/* global actor */

'use strict'
// in this file you can append custom step methods to 'I' object

// converts e.g. 'root[label][0][text]' to 'root.label.0.text'
const nameToSchemaPath = function (name) {
  name = name.replaceAll('][','.') // 'root[label.0.text]'
  name = name.replaceAll('[','.') // 'root.label.0.text]'
  name = name.replaceAll(']','') // 'root.label.0.text'
  return name
}

// converts e.g. 'root.label.0.text' to 'root[label][0][text]'
const schemaPathToName = function (schemapath) {
  schemapath = schemapath.replace('.','[') // 'root[label.0.text.'
  schemapath = schemapath.replaceAll('.','][') // 'root[label][0][text'
  if (schemapath.includes('[')) schemapath += ']' // 'root[label][0][text]'
  return schemapath
}

const normalizeParams = function (params) {
  if (!params.schemapath && params.name) params.schemapath = nameToSchemaPath(params.name)
  if (params.schemapath && !params.name) params.name = schemaPathToName(params.schemapath)
}

module.exports = function () {
  return actor({

    // Define custom steps here, use 'this' to access default methods of this.
    // It is recommended to place a general 'login' function here.
    // Note: 'I' needs to replaced with 'this' here
    login: async function () {
      const I = this
      I.amOnPage('/wiki/Special:UserLogin');
      await I.enableCursor()
      I.see('Log in');
      I.moveCursorTo('#wpName1')
      I.fillField('#wpName1', 'Admin');
      I.moveCursorTo('#wpPassword1')
      I.fillField('#wpPassword1', process.env.MW_ADMIN_PASS);
      I.moveCursorTo('#wpRemember')
      I.checkOption('#wpRemember');
      I.moveCursorTo('#wpLoginAttempt')
      I.click('#wpLoginAttempt')
    },

    // Login in to hidden local login form (e.g. when OIDC is prefered for users)
    loginHidden: async function () {
      const I = this
      I.amOnPage('/wiki/Special:UserLogin');
      I.executeScript(`document.querySelector('#wpName1').style.display = 'block'`)
      I.executeScript(`document.querySelector('#wpPassword1').style.display = 'block'`)
      I.executeScript(`document.querySelector('#wpRemember').style.display = 'block'`)
      I.executeScript(`document.querySelector('#wpLoginAttempt').style.display = 'block'`)
      I.fillField('#wpName1', 'Admin');
      I.fillField('#wpPassword1', process.env.MW_ADMIN_PASS);
      I.checkOption('#wpRemember');
      I.click('#wpLoginAttempt')
    },

    enableCursor: async function () {
      const I = this
      // highlight curser: https://stackoverflow.com/questions/53900972/how-can-i-see-the-mouse-pointer-as-it-performs-actions-in-selenium
      const enable_cursor = `
      function enableCursor() {
        var seleniumFollowerImg = document.createElement("img");
        seleniumFollowerImg.setAttribute('src', 'data:image/png;base64,'
          + 'iVBORw0KGgoAAAANSUhEUgAAABQAAAAeCAQAAACGG/bgAAAAAmJLR0QA/4ePzL8AAAAJcEhZcwAA'
          + 'HsYAAB7GAZEt8iwAAAAHdElNRQfgAwgMIwdxU/i7AAABZklEQVQ4y43TsU4UURSH8W+XmYwkS2I0'
          + '9CRKpKGhsvIJjG9giQmliHFZlkUIGnEF7KTiCagpsYHWhoTQaiUUxLixYZb5KAAZZhbunu7O/PKf'
          + 'e+fcA+/pqwb4DuximEqXhT4iI8dMpBWEsWsuGYdpZFttiLSSgTvhZ1W/SvfO1CvYdV1kPghV68a3'
          + '0zzUWZH5pBqEui7dnqlFmLoq0gxC1XfGZdoLal2kea8ahLoqKXNAJQBT2yJzwUTVt0bS6ANqy1ga'
          + 'VCEq/oVTtjji4hQVhhnlYBH4WIJV9vlkXLm+10R8oJb79Jl1j9UdazJRGpkrmNkSF9SOz2T71s7M'
          + 'SIfD2lmmfjGSRz3hK8l4w1P+bah/HJLN0sys2JSMZQB+jKo6KSc8vLlLn5ikzF4268Wg2+pPOWW6'
          + 'ONcpr3PrXy9VfS473M/D7H+TLmrqsXtOGctvxvMv2oVNP+Av0uHbzbxyJaywyUjx8TlnPY2YxqkD'
          + 'dAAAAABJRU5ErkJggg==');
        seleniumFollowerImg.setAttribute('id', 'selenium_mouse_follower');
        seleniumFollowerImg.setAttribute('style', 'position: absolute; z-index: 99999999999; pointer-events: none; left:0; top:0; width: 50px; height: auto' );
        document.body.appendChild(seleniumFollowerImg);
        document.onmousemove = function (e) {
          document.getElementById("selenium_mouse_follower").style.left = e.pageX + 'px';
          document.getElementById("selenium_mouse_follower").style.top = e.pageY + 'px';
        };
      };

      enableCursor();
      `
      await I.executeScript(enable_cursor);
    },

    seeElementInViewport: async function (params) {
      const I = this
      // https://stackoverflow.com/questions/123999/how-can-i-tell-if-a-dom-element-is-visible-in-the-current-viewport
      const script = `
        var el = null;
        try {
          el = document.querySelector('${params.selector}');
        } catch (e) {
          el = document.evaluate('${params.selector}', document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
        }
        var rect = el.getBoundingClientRect();
    
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) && /* or $(window).height() */
            rect.right <= (window.innerWidth || document.documentElement.clientWidth) /* or $(window).width() */
        );
      `
      return await I.executeScript(script)
    },

    updateEditorId: async function () {
      const I = this
      if (this.getEditorLevel() === -1) this.editorId = null
      else this.editorId = await I.executeScript('return document.querySelectorAll(".je-ready")[' + this.getEditorLevel() + '].id;')
      return this.editorId
    },

    incrementEditorLevel: async function () {
      //console.log("incrementEditorLevel from ", this.editorLevel)
      if (typeof this.editorLevel === 'undefined') this.editorLevel = -1
      this.editorLevel += 1
      //console.log(" to ",this.editorLevel)
      await this.updateEditorId()
      return this.editorLevel
    },

    decrementEditorLevel: async function () {
      //console.log("decrementEditorLevel from ", this.editorLevel)
      if (typeof this.editorLevel === 'undefined') this.editorLevel = 1
      this.editorLevel -= 1
      //console.log(" to ",this.editorLevel)
      await this.updateEditorId()
      return this.editorLevel 
    },

    getEditorLevel: function () {
      if (typeof this.editorLevel === 'undefined') this.editorLevel = 0
      return this.editorLevel
    },

    addNotification: async function (params) {
      const I = this
      params.timeout = params.timeout || 3000
      const script = `
      var div = document.createElement('div');
      div.id = 'codeceptjs-toast-msg';
      div.textContent = "";
      div.style = \`
        visibility: visible;
        min-width: 300px;
        background-color: #333;
        color: #fff;
        text-align: center;
        border-radius: 2px;
        padding: 8px;
        position: fixed;
        z-index: 1000;
        left: 50%;
        bottom: 15px;
        margin-left: -150px;
      \`;
      if (!document.getElementById('codeceptjs-toast-msg')) document.body.append(div);
      document.getElementById('codeceptjs-toast-msg').style.visibility = 'visible';
      document.getElementById('codeceptjs-toast-msg').textContent = \`${params.text}\`;
      setTimeout(function(){ 
        document.getElementById('codeceptjs-toast-msg').style.visibility = 'hidden';
      }, ${params.timeout});
      `;
      return await I.executeScript(script)
    },

    scrollAndMove: async function (params) {
      const I = this
      
      // exception handling does not work, so we need to check if element is outside viewport first
      let inViewPort = await I.seeElementInViewport(params)
      // scroll only if needed
      if (!inViewPort) {
        I.scrollIntoView(params.selector)
       } 
       I.moveCursorTo(params.selector)
    },

    scrollAndMoveAndClick: async function (params) {
      const I = this
      await I.scrollAndMove(params)
      //I.waitForClickable(params.selector, 30); // notifications may obscure target element temporary
      I.click(params.selector)
    },

    scrollAndMoveAndFillField: async function (params) {
      const I = this
      await I.scrollAndMove(params)
      I.fillField(params.selector, params.value)
    },

    scrollAndMoveAndCheckOption: async function (params) {
      const I = this
      await I.scrollAndMove(params)
      I.checkOption(params.selector)
    },

    openCreateInstanceForm: async function (params) {
      const I = this
      I.amOnPage('/wiki/' + params.category);
      this.editorLevel = -1
      await I.addNotification({text: "Navigate to the Category and click 'Create Instance'"})
      await I.enableCursor()
      I.moveCursorTo('#ca-create-instance')
      I.wait(3)
      I.click('#ca-create-instance')
      I.waitForElement('.je-ready', 5) // secs
      I.moveCursorTo('.je-ready .card-title')
      await I.incrementEditorLevel();
    },

    openEditInstanceForm: async function (params) {
      const I = this
      I.amOnPage('/wiki/' + params.title);
      this.editorLevel = -1
      await I.addNotification({text: "Navigate to the Item and click 'Edit Data'"})
      await I.enableCursor()
      I.moveCursorTo('#ca-edit-data')
      I.wait(3)
      I.click('#ca-edit-data')
      I.waitForElement('.je-ready', 5) // secs
      I.moveCursorTo('.je-ready .card-title')
      await I.incrementEditorLevel();
    },

    addAdditionalProperty: async function (params) {
      let legacy_mode = false
      if (params.name) legacy_mode = true
      const I = this
      normalizeParams(params)
      await I.addNotification({text: "Select the property from the list"})
      await I.scrollAndMoveAndClick({selector: '.json-editor-btntype-properties'})
      if (legacy_mode) I.checkOption(params.name) // older json-editor version didn't create id, only property title is set as label
      else I.scrollAndMoveAndCheckOption({ selector: '#' + params.schemapath.replace(/(\.)(?!.*\1)/, '-')}) // replace last '.' with '-'
      await I.scrollAndMoveAndClick({selector: '.json-editor-btntype-properties'})
    },

    createInline: async function (params) {
      const I = this
      normalizeParams(params)
      I.moveCursorTo('[data-schemapath="' + params.schemapath + '"] .inline-edit-btn')
      I.click('[data-schemapath="' + params.schemapath + '"] .inline-edit-btn')
      // xPath index is 1-based
      I.waitForVisible('(//*[@class="je-ready"])['+ (this.getEditorLevel() + 2) + ']', 10);

      await I.incrementEditorLevel();
    },

    saveEditor: async function () {
      const I = this
      await I.addNotification({text: "Save your changes"})
      //const editorId = await I.executeScript('return document.querySelectorAll(".je-ready")[1].id;')
      I.click('#' + this.editorId + ' .card-title.level-1')
      const xpath = '(//*[@class="je-ready"])[' + (this.getEditorLevel() + 1) + ']/ancestor::*[contains(@class,"oo-ui-window-content")]//*[contains(@class,"oo-ui-processDialog-actions-primary")]//*[contains(@class,"oo-ui-buttonElement-button")]'
      await I.scrollAndMoveAndClick({selector: xpath})
      // somehow I.click does not work here => run click() manually
      //await I.executeScript('document.querySelectorAll(".je-ready")[' + (this.getEditorLevel()) + '].closest(".oo-ui-window-content").querySelector(".oo-ui-processDialog-actions-primary .oo-ui-buttonElement-button").click()')
      //I.retry({ retries: 5, maxTimeout: 1000 }).dontSee('#' + editorId)
      if ((await I.grabNumberOfVisibleElements('.oo-ui-messageDialog-content')) > 0) {
        await I.scrollAndMoveAndClick({selector:'(//*[contains(@class,"oo-ui-messageDialog-content")]//*[@class="oo-ui-buttonElement-button"])[2]'}) // OK button
      }
      I.waitForInvisible('#' + this.editorId, 10);
      // xPath index is 1-based
      //I.waitForInvisible('(//*[@class="je-ready"])['+ (this.getEditorLevel() + 1) + ']', 10);
      await this.decrementEditorLevel()
      I.wait(1)
      //click away notifications
      if ((await I.grabNumberOfVisibleElements('.mw-notification-title')) > 0) {
        await I.scrollAndMoveAndClick({selector:'.mw-notification-title'})
      }
      if ((await I.grabNumberOfVisibleElements('.mw-notification-content')) > 0) {
        await I.scrollAndMoveAndClick({selector:'.mw-notification-content'})
      }

    },

    cancelEditor: async function () {
      const I = this
      //const editorId = await I.executeScript('return document.querySelectorAll(".je-ready")[1].id;')
      //I.click('#' + this.editorId + ' .card-title.level-1')
      const xpath = '(//*[@class="je-ready"])[' + (this.getEditorLevel() + 1) + ']/ancestor::*[contains(@class,"oo-ui-window-content")]//*[contains(@class,"oo-ui-processDialog-actions-safe")]//*[contains(@class,"oo-ui-buttonElement-button")]'
      await I.scrollAndMoveAndClick({selector: xpath})
      // somehow I.click does not work here => run click() manually
      //await I.executeScript('document.querySelectorAll(".je-ready")[' + (this.getEditorLevel()) + '].closest(".oo-ui-window-content").querySelector(".oo-ui-processDialog-actions-safe .oo-ui-buttonElement-button").click()')
      
      //I.retry({ retries: 5, maxTimeout: 1000 }).dontSee('#' + editorId)
      I.waitForInvisible('#' + this.editorId, 10);
      // xPath index is 1-based
      //I.waitForInvisible('(//*[@class="je-ready"])['+ (this.getEditorLevel() + 1) + ']', 10);
      await this.decrementEditorLevel()
      I.wait(1)
    },

    fillEditorField: async function (params) {
      const I = this
      normalizeParams(params)
      await I.scrollAndMoveAndFillField({selector: '#' + this.editorId + ' [name="' + params.name + '"]', value: params.value})
    },

    addArrayElement: async function (params) {
      const I = this
      await I.scrollAndMoveAndClick({selector: '#' + this.editorId + ' [data-schemapath="' + params.schemapath + '"] .json-editor-btn-add'})
    },


    selectAutocompleteResult: async function (params) {
      const I = this
      await I.scrollAndMoveAndClick({selector: '#' + this.editorId + ' [data-schemapath="' + params.schemapath + '"]'})
      if (params.input) I.type(params.input)
      I.wait(5)
      await I.scrollAndMoveAndClick({selector: '#' + this.editorId + ' [data-schemapath="' + params.schemapath + '"] #autocomplete-result-' + params.index})
      I.wait(1)
    },

    assertFieldHasValue: async function (params) {
      const I = this
      normalizeParams(params)
      const value = await I.executeScript(`return document.querySelector('[name="` + params.name + `"]').value`)
      I.assertEqual(value, params.expected);
    },

    assertNotFieldHasValue: async function (params) {
      const I = this
      normalizeParams(params)
      const value = await I.executeScript(`return document.querySelector('[name="` + params.name + `"]').value`)
      I.assertNotEqual(value, params.value);
    }
  })
}