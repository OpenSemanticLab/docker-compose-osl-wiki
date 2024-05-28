Feature('OSL up and running');





Before(async ({ I }) => {
  await I.addNotification({ text: "Login first" })
  await I.login()
});

Scenario('check main page', ({ I }) => {
  I.amOnPage('/wiki/Main_Page');
  I.see('Main Page');
});

crypto = require('node:crypto');
function uuidv4() {
  return "10000000-1000-4000-8000-100000000000".replace(/[018]/g, c =>
    (+c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> +c / 4).toString(16)
  );
}

Scenario('test search bar', async ({ I }) => {
  I.amOnPage('/');
  const uuid = uuidv4().split('-')[0] +  " AAABxxx" // crypto.randomUUID()
  const uuid2 = uuidv4().split('-')[0] +  " some text in the article body" // crypto.randomUUID()
  await I.openCreateInstanceForm({ category: 'Category:OSW92cc6b1a2e6b4bb7bad470dfdcfdaf26' }); // Article
  await I.addNotification({text: "Assign at least a label to your article"})
  await I.fillEditorField({ schemapath: 'root.label.0.text', value: uuid })
  await I.saveEditor()
  I.wait(3)
  
  await I.addNotification({text: "Write some text in your new Article"})
  await I.scrollAndMoveAndClick({selector: '#ca-ve-edit'});
  I.waitForElement('.oo-ui-tool-name-showSave', 10)
  I.waitForClickable('.oo-ui-tool-name-showSave', 10)
  I.type(`${uuid2}`, 100) // 100 ms delay
  I.wait(2)
  await I.scrollAndMoveAndClick({selector: '.oo-ui-tool-name-showSave'})
  await I.scrollAndMoveAndFillField({selector:'.ve-ui-mwSaveDialog-summary textarea', value:'Some test content'})
  await I.scrollAndMoveAndClick({selector: '.oo-ui-processDialog-actions-primary'})
  I.wait(3)

  I.amOnPage('/');
  await I.addNotification({text: "Click on the search icon"})
  await I.scrollAndMoveAndClick({selector:'#citizen-search__buttonCheckbox'});
  I.wait(1)
  await I.addNotification({text: "You can search for (parts of) your label"})
  await I.scrollAndMoveAndFillField({selector:'#searchInput', value: uuid});
  I.wait(2)
  await I.scrollAndMoveAndClick({selector:'#citizen-typeahead-suggestion-0'})
  I.see(uuid)

  I.amOnPage('/');
  await I.scrollAndMoveAndClick({selector:'#citizen-search__buttonCheckbox'});
  I.wait(1)
  await I.addNotification({text: "... Which also works case-insensitive"})
  await I.scrollAndMoveAndFillField({selector:'#searchInput', value: uuid.toUpperCase()});
  I.wait(2)
  await I.scrollAndMoveAndClick({selector:'#citizen-typeahead-suggestion-0'})
  I.see(uuid)

  I.amOnPage('/');
  await I.scrollAndMoveAndClick({selector:'#citizen-search__buttonCheckbox'});
  I.wait(1)
  await I.scrollAndMoveAndFillField({selector:'#searchInput', value: uuid.toLowerCase()});
  I.wait(2)
  await I.scrollAndMoveAndClick({selector:'#citizen-typeahead-suggestion-0'})
  I.see(uuid)

  /* Fails
  I.amOnPage('/');
  await I.addNotification({text: "Click on the search icon"})
  await I.scrollAndMoveAndClick({selector:'#citizen-search__buttonCheckbox'});
  I.wait(1)
  await I.addNotification({text: "You can search for content in the article body using the fulltext search"})
  await I.scrollAndMoveAndFillField({selector:'#searchInput', value: uuid2});
  I.wait(2)
  await I.scrollAndMoveAndClick({selector:'#citizen-typeahead-fulltext'})
  I.wait(2)
  await I.scrollAndMoveAndClick({selector:'(//*[@class="mw-search-result"]//a)[1]'}) //*[@id="mw-content-text"]/div[3]/ul/li/div[1]/a
  I.see(uuid2)*/
});

Scenario('print article', async ({ I }) => {
  I.amOnPage('/');
  const uuid = uuidv4().split('-')[0] +  " AAABxxx" // crypto.randomUUID()
  const uuid2 = uuidv4().split('-')[0] +  " some text in the article body" // crypto.randomUUID()
  await I.openCreateInstanceForm({ category: 'Category:OSW92cc6b1a2e6b4bb7bad470dfdcfdaf26' }); // Article
  await I.addNotification({text: "Assign at least a label to your article"})
  await I.fillEditorField({ schemapath: 'root.label.0.text', value: uuid })
  await I.saveEditor()
  I.wait(3)
  
  await I.addNotification({text: "Write some text in your new Article"})
  await I.scrollAndMoveAndClick({selector: '#ca-ve-edit'});
  I.waitForElement('.oo-ui-tool-name-showSave', 10)
  I.waitForClickable('.oo-ui-tool-name-showSave', 10)
  I.type(`${uuid2}`, 100) // 100 ms delay
  I.wait(2)
  await I.scrollAndMoveAndClick({selector: '.oo-ui-tool-name-showSave'})
  await I.scrollAndMoveAndFillField({selector:'.ve-ui-mwSaveDialog-summary textarea', value:'Some test content'})
  await I.scrollAndMoveAndClick({selector: '.oo-ui-processDialog-actions-primary'})
  I.wait(3)

  //click away notifications
  while ((await I.grabNumberOfVisibleElements('.mw-notification')) > 0) {
    await I.scrollAndMoveAndClick({selector:'.mw-notification'})
    I.wait(1)
  }

  for (let i = 0; i < 1; i++) {
    await I.scrollAndMoveAndClick({selector: '#page-actions-more__checkbox'});
    await I.scrollAndMoveAndClick({selector: '#ca-export-pdf'})
    I.wait(3)
    // PDF opens in new tab
    //I.amInPath('output/downloads');
    //I.seeFile(uuid + '.jpg');
    I.see(uuid2)
    I.wait(1)
    //I.switchToPreviousTab() // somehow the PDF tab is not regarded as active tab
    I.closeOtherTabs();
  }

});

Scenario('Create ELN entry', async ({ I }) => {
  for (let loop_index = 0; loop_index < 1; loop_index++) {
    I.amOnPage('/wiki/Main_Page');
    await I.openCreateInstanceForm({ category: 'Category:OSW0e7fab2262fb4427ad0fa454bc868a0d' }); // ElnEntry

    await I.fillEditorField({ schemapath: 'root.label.0.text', value: 'Test label' })

    await I.addAdditionalProperty({ schemapath: 'root.orderer' })
    // await I.selectAutocompleteResult({ schemapath: 'root.orderer', input: 'test', index: 0 })
    await I.fillEditorField({ schemapath: 'root.orderer', value: '' })
    await I.createInline({ schemapath: "root.orderer" })
    await I.cancelEditor()
    await I.createInline({ schemapath: "root.orderer" })
    let orgName = 'Test Org label ' + loop_index
    await I.fillEditorField({ schemapath: 'root.label.0.text', value: orgName })
    await I.saveEditor()
    await I.assertFieldHasValue({ schemapath: "root.orderer", expected: orgName })

    await I.addAdditionalProperty({ schemapath: "root.actionees" })
    await I.addArrayElement({ schemapath: "root.actionees" })
    await I.fillEditorField({ schemapath: 'root.actionees.0', value: '' })
    await I.createInline({ schemapath: "root.actionees.0" })
    let actName = 'Person ' + loop_index
    await I.fillEditorField({ schemapath: 'root.first_name', value: 'Test' })
    await I.fillEditorField({ schemapath: 'root.surname', value: actName })
    await I.saveEditor()
    await I.assertFieldHasValue({ schemapath: "root.actionees.0", expected: 'Test ' + actName })

    //pause()
    await I.saveEditor()
    I.wait(3)

  }
});
