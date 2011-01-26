<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class PhabricatorMetaMTASendController extends PhabricatorMetaMTAController {

  public function processRequest() {

    $request = $this->getRequest();

    if ($request->isFormPost()) {
      $mail = new PhabricatorMetaMTAMail();
      $mail->addTos($request->getArr('to'));
      $mail->addCCs($request->getArr('cc'));
      $mail->setSubject($request->getStr('subject'));
      $mail->setBody($request->getStr('body'));

      // TODO!
//      $mail->setFrom($request->getViewerContext()->getUserID());
      $mail->setSimulatedFailureCount($request->getInt('failures'));
      $mail->setIsHTML($request->getInt('html'));
      $mail->save();
      if ($request->getInt('immediately')) {
        $mail->sendNow($force_send = true);
      }

      return id(new AphrontRedirectResponse())
        ->setURI('/mail/view/'.$mail->getID().'/');
    }

    $failure_caption =
      "Enter a number to simulate that many consecutive send failures before ".
      "really attempting to deliver via the underlying MTA.";


    $form = new AphrontFormView();
    $form->setAction('/mail/send/');
    $form
      ->appendChild(
        '<p class="aphront-form-instructions">This form will send a normal '.
        'email using MetaMTA as a transport mechanism.</p>')
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel('To')
          ->setName('to')
          ->setDatasource('/typeahead/common/user/'))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel('CC')
          ->setName('cc')
          ->setDatasource('/typeahead/common/user/'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Subject')
          ->setName('subject'))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Body')
          ->setName('body'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Simulate Failures')
          ->setName('failures')
          ->setCaption($failure_caption))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->setLabel('HTML')
          ->addCheckbox('html', '1', 'Send as HTML email.'))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->setLabel('Send Now')
          ->addCheckbox(
            'immediately',
            '1',
            'Send immediately, not via MetaMTA background script.'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Send Mail'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Send Email');
    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_WIDE);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Send Mail',
      ));
  }

}
