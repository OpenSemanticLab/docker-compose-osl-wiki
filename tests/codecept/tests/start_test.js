Feature('OSL up and running');

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

Scenario('check main page', ({ I }) => {
  I.amOnPage('/');
  I.see('Main Page');
});

Scenario('login', ({ I }) => {
    I.amOnPage('/wiki/Special:UserLogin');
    I.see('Log in');
    I.fillField('#wpName1', 'Admin');
    I.fillField('#wpPassword1', process.env.MW_ADMIN_PASS);
    I.checkOption('#wpRemember');
     //wpRemember
     //wpLoginAttempt

    I.click('#wpLoginAttempt')
    I.waitForElement('#Semantic_Lab', 5); // section on Main page
    //I.amOnPage('/wiki/Main_Page')

  });
  

  Scenario('Create ELN entry', async ({ I }) => {
    for (let loop_index = 0; loop_index < 3; loop_index++) {
    I.amOnPage('/');  
    I.amOnPage('/wiki/Category:OSW0e7fab2262fb4427ad0fa454bc868a0d'); // ElnEntry
    I.executeScript(enable_cursor);
    I.moveCursorTo('#ca-create-instance')
    I.wait(3)
    I.click('#ca-create-instance')
    I.waitForElement('.je-ready', 5) // secs
    I.moveCursorTo('.je-ready')
    let editorId = I.executeScript('document.querySelectorAll(".je-ready")[0].id')
    I.fillField('root[label][0][text]', 'Test label')
    I.moveCursorTo('.json-editor-btntype-properties')
    I.click('.json-editor-btntype-properties')
    I.moveCursorTo('#root-orderer')
    I.checkOption('#root-orderer')
    I.click('.json-editor-btntype-properties')
    //I.waitForElement('.inline-edit-btn', 1); // secs
    I.click('[data-schemapath="root.orderer"] .inline-edit-btn')
    //I.waitForElement({ css: '#root[orderer]'}, 1); // secs
    //I.seeElement({ css: '#root[orderer]'})
    //I.click({ css: '#root[orderer]'})
    //I.waitForElement('.inline-edit-btn', 1); // secs
    //I.seeElement({ css: '.inline-edit-btn'})
    //I.click({ css: '.inline-edit-btn'})
    //I.wait(3)
    //I.executeScript('document.querySelectorAll(".inline-edit-btn")[0].click();');
    //I.wait(5)

    I.retry({ retries: 2, maxTimeout: 3000 }).see('OrganizationalUnit')
    let editorId2 = await I.executeScript('return document.querySelectorAll(".je-ready")[1].id;')
    let orgName = 'Test Org label ' + loop_index 
    I.fillField('#' + editorId2 + ' [name="root[label][0][text]"]', orgName)
    //let saveBtn = locate('.oo-ui-window-content').withChild('#' + editorId2).find('.oo-ui-buttonElement-button').withText('Save');
    I.click('#' + editorId2)
    await I.executeScript('document.querySelectorAll(".je-ready")[1].closest(".oo-ui-window-content").querySelector(".oo-ui-buttonElement-button").click()')
    I.retry({ retries: 5, maxTimeout: 1000 }).seeElement('[data-schemapath="root.orderer"]')
    //pause()
    I.wait(10)
    //I.retry({ retries: 10, maxTimeout: 1000 }).seeInField('[name="root[orderer]"', 'Test Org label');
    //I.retry({ retries: 10, maxTimeout: 1000 }).see('Test Org label', '[data-schemapath="root.orderer"]');
    let orderer = await I.executeScript(`return document.querySelector('[name="root[orderer]"').value`)
    //assert.equal(orderer, orgName);
    I.assertEqual(orderer, orgName);
    //I.click(saveBtn)
    //I.wait(5)
    //pause()
    }
  });
