Feature('OSL up and running');





Before(({ I }) => {
  I.login()
});

/*Scenario('check main page', ({ I }) => {
  I.amOnPage('/');
  I.see('Main Page');
});*/

Scenario('Create ELN entry', async ({ I }) => {
  for (let loop_index = 0; loop_index < 1; loop_index++) {
    I.amOnPage('/');
    await I.openCreateInstanceForm({category: 'Category:OSW0e7fab2262fb4427ad0fa454bc868a0d'}); // ElnEntry

    await I.fillEditorField({name: 'root[label][0][text]', value:'Test label'})

    I.addAdditionalProperty({name: "Orderer"})
    //I.waitForElement('.inline-edit-btn', 1); // secs
    //I.click('[name="root.orderer"]')
    //I.click('root[orderer]')
    //I.wait(3)
    //I.click('[data-schemapath="root.orderer"] #autocomplete-result-0')
    //I.wait(1)
    await I.selectAutocompleteResult({schemapath: 'root.orderer', input:'test', index:0})
    await I.fillEditorField({name: 'root[orderer]', value:''})

    await I.createInline({schemapath: "root.orderer"})
    await I.cancelEditor()
    await I.createInline({schemapath: "root.orderer"})

    //let editorId2 = await I.executeScript('return document.querySelectorAll(".je-ready")[1].id;')
    let orgName = 'Test Org label ' + loop_index
    //I.fillField('#' + editorId2 + ' [name="root[label][0][text]"]', orgName)
    await I.fillEditorField({name: 'root[label][0][text]', value: orgName})
    //let saveBtn = locate('.oo-ui-window-content').withChild('#' + editorId2).find('.oo-ui-buttonElement-button').withText('Save');
    await I.saveEditor()
    //I.retry({ retries: 5, maxTimeout: 1000 }).seeElement('[data-schemapath="root.orderer"]')
    //pause()
    //I.wait(10)

    await I.assertFieldHasValue({name: "root[orderer]", expected: orgName})
    //await I.executeScript('document.querySelectorAll(".je-ready")[0].closest(".oo-ui-window-content").querySelector(".oo-ui-buttonElement-button").click()')
    await I.saveEditor()
    I.wait(3)
    //pause()
  }
});
