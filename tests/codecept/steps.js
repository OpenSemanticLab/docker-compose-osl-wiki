/* global actor */

'use strict'
// in this file you can append custom step methods to 'I' object

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
          seleniumFollowerImg.setAttribute('style', 'position: absolute; z-index: 99999999999; pointer-events: none; left:0; top:0');
          document.body.appendChild(seleniumFollowerImg);
          document.onmousemove = function (e) {
            document.getElementById("selenium_mouse_follower").style.left = e.pageX + 'px';
            document.getElementById("selenium_mouse_follower").style.top = e.pageY + 'px';
          };
        };

        enableCursor();
`

module.exports = function () {
  return actor({

    // Define custom steps here, use 'this' to access default methods of this.
    // It is recommended to place a general 'login' function here.
    // Note: 'I' needs to replaced with 'this' here
    login: function () {
      const I = this
      I.enableCursor()
      I.amOnPage('/wiki/Special:UserLogin');
      I.see('Log in');
      I.moveCursorTo('#wpName1')
      I.fillField('#wpName1', 'Admin');
      I.moveCursorTo('#wpPassword1')
      I.fillField('#wpPassword1', process.env.MW_ADMIN_PASS);
      I.moveCursorTo('#wpRemember')
      I.checkOption('#wpRemember');
      //wpRemember
      //wpLoginAttempt
      I.moveCursorTo('#wpLoginAttempt')
      I.click('#wpLoginAttempt')
      //I.waitForElement('#Semantic_Lab', 5); // section on Main page
      //I.amOnPage('/wiki/Main_Page')
    },

    enableCursor: function () {
      const I = this
      I.executeScript(enable_cursor);
    },

    updateEditorId: async function () {
      const I = this
      if (this.getEditorLevel() === -1) this.editorId = null
      else this.editorId = await I.executeScript('return document.querySelectorAll(".je-ready")[' + this.getEditorLevel() + '].id;')
      return this.editorId
    },

    incrementEditorLevel: async function () {
      console.log("incrementEditorLevel from ", this.editorLevel)
      if (typeof this.editorLevel === 'undefined') this.editorLevel = -1
      this.editorLevel += 1
      console.log(" to ",this.editorLevel)
      await this.updateEditorId()
      return this.editorLevel
    },

    decrementEditorLevel: async function () {
      console.log("decrementEditorLevel from ", this.editorLevel)
      if (typeof this.editorLevel === 'undefined') this.editorLevel = 1
      this.editorLevel -= 1
      console.log(" to ",this.editorLevel)
      await this.updateEditorId()
      return this.editorLevel 
    },

    getEditorLevel: function () {
      if (typeof this.editorLevel === 'undefined') this.editorLevel = 0
      return this.editorLevel
    },

    openCreateInstanceForm: async function (params) {
      const I = this
      I.enableCursor()
      I.amOnPage('/wiki/' + params.category);
      I.moveCursorTo('#ca-create-instance')
      I.wait(3)
      I.click('#ca-create-instance')
      I.waitForElement('.je-ready', 5) // secs
      I.moveCursorTo('.je-ready')
      await I.incrementEditorLevel();
    },

    addAdditionalProperty: function (params) {
      const I = this
      I.moveCursorTo('.json-editor-btntype-properties')
      I.click('.json-editor-btntype-properties')
      //I.moveCursorTo('#root-orderer')
      //I.checkOption('#root-orderer')
      I.checkOption(params.name)
      I.click('.json-editor-btntype-properties')
    },

    createInline: async function (params) {
      const I = this
      I.moveCursorTo('[data-schemapath="' + params.schemapath + '"] .inline-edit-btn')
      I.click('[data-schemapath="' + params.schemapath + '"] .inline-edit-btn')
      //I.waitForElement({ css: '#root[orderer]'}, 1); // secs
      //I.seeElement({ css: '#root[orderer]'})
      //I.click({ css: '#root[orderer]'})
      //I.waitForElement('.inline-edit-btn', 1); // secs
      //I.seeElement({ css: '.inline-edit-btn'})
      //I.click({ css: '.inline-edit-btn'})
      //I.wait(3)
      //I.executeScript('document.querySelectorAll(".inline-edit-btn")[0].click();');
      //I.wait(5)

      //I.retry({ retries: 5, maxTimeout: 1000 }).see('OrganizationalUnit')
      //I.retry({ retries: 5, maxTimeout: 1000 }).see('#' + editorId)
      // xPath index is 1-based
      I.waitForVisible('(//*[@class="je-ready"])['+ (this.getEditorLevel() + 2) + ']', 10);

      await I.incrementEditorLevel();
    },

    saveEditor: async function () {
      const I = this
      //const editorId = await I.executeScript('return document.querySelectorAll(".je-ready")[1].id;')
      I.click('#' + this.editorId + ' .card-title.level-1')
      await I.executeScript('document.querySelectorAll(".je-ready")[' + (this.getEditorLevel()) + '].closest(".oo-ui-window-content").querySelector(".oo-ui-processDialog-actions-primary .oo-ui-buttonElement-button").click()')
      //I.retry({ retries: 5, maxTimeout: 1000 }).dontSee('#' + editorId)
      I.waitForInvisible('#' + this.editorId, 10);
      // xPath index is 1-based
      //I.waitForInvisible('(//*[@class="je-ready"])['+ (this.getEditorLevel() + 1) + ']', 10);
      await this.decrementEditorLevel()
      I.wait(1)
    },

    cancelEditor: async function () {
      const I = this
      //const editorId = await I.executeScript('return document.querySelectorAll(".je-ready")[1].id;')
      //I.click('#' + this.editorId + ' .card-title.level-1')
      await I.executeScript('document.querySelectorAll(".je-ready")[' + (this.getEditorLevel()) + '].closest(".oo-ui-window-content").querySelector(".oo-ui-processDialog-actions-safe .oo-ui-buttonElement-button").click()')
      //I.retry({ retries: 5, maxTimeout: 1000 }).dontSee('#' + editorId)
      I.waitForInvisible('#' + this.editorId, 10);
      // xPath index is 1-based
      //I.waitForInvisible('(//*[@class="je-ready"])['+ (this.getEditorLevel() + 1) + ']', 10);
      await this.decrementEditorLevel()
      I.wait(1)
    },
    
    fillEditorField: async function (params) {
      const I = this
      I.moveCursorTo('#' + this.editorId + ' [name="' + params.name + '"]')
      I.fillField('#' + this.editorId + ' [name="' + params.name + '"]', params.value)
    },

    selectAutocompleteResult: async function (params) {
      const I = this
      I.moveCursorTo('#' + this.editorId + ' [data-schemapath="' + params.schemapath + '"]')
      I.click('#' + this.editorId + ' [data-schemapath="' + params.schemapath + '"]', params.value)
      if (params.input) I.type(params.input)
      I.wait(3)
      I.moveCursorTo('#' + this.editorId + ' [data-schemapath="' + params.schemapath + '"] #autocomplete-result-' + params.index)
      I.click('#' + this.editorId + ' [data-schemapath="' + params.schemapath + '"] #autocomplete-result-' + params.index)
      I.wait(1)
    },

    assertFieldHasValue: async function (params) {
      const I = this
      //I.retry({ retries: 10, maxTimeout: 1000 }).seeInField('[name="root[orderer]"', 'Test Org label');
      //I.retry({ retries: 10, maxTimeout: 1000 }).see('Test Org label', '[data-schemapath="root.orderer"]');
      const value = await I.executeScript(`return document.querySelector('[name="` + params.name + `"]').value`)
      //assert.equal(orderer, orgName);
      I.assertEqual(value, params.expected);
    }
  })
}