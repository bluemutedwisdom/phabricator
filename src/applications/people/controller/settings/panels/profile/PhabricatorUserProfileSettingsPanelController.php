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

class PhabricatorUserProfileSettingsPanelController
  extends PhabricatorUserSettingsPanelController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $profile = id(new PhabricatorUserProfile())->loadOneWhere(
      'userPHID = %s',
      $user->getPHID());
    if (!$profile) {
      $profile = new PhabricatorUserProfile();
      $profile->setUserPHID($user->getPHID());
    }

    $errors = array();
    if ($request->isFormPost()) {
      $profile->setTitle($request->getStr('title'));
      $profile->setBlurb($request->getStr('blurb'));

      if (!empty($_FILES['image'])) {
        $err = idx($_FILES['image'], 'error');
        if ($err != UPLOAD_ERR_NO_FILE) {
          $file = PhabricatorFile::newFromPHPUpload(
            $_FILES['image'],
            array(
              'authorPHID' => $user->getPHID(),
            ));
          $okay = $file->isTransformableImage();
          if ($okay) {
            $xformer = new PhabricatorImageTransformer();

            // Generate the large picture for the profile page.
            $large_xformed = $xformer->executeProfileTransform(
              $file,
              $width = 280,
              $min_height = 140,
              $max_height = 420);
            $profile->setProfileImagePHID($large_xformed->getPHID());

            // Generate the small picture for comments, etc.
            $small_xformed = $xformer->executeProfileTransform(
              $file,
              $width = 50,
              $min_height = 50,
              $max_height = 50);
            $user->setProfileImagePHID($small_xformed->getPHID());
          } else {
            $errors[] =
              'Only valid image files (jpg, jpeg, png or gif) '.
              'will be accepted.';
          }
        }
      }

      if (!$errors) {
        $user->save();
        $profile->save();
        $response = id(new AphrontRedirectResponse())
          ->setURI('/settings/page/profile/?saved=true');
        return $response;
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle('Form Errors');
      $error_view->setErrors($errors);
    } else {
      if ($request->getStr('saved')) {
        $error_view = new AphrontErrorView();
        $error_view->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
        $error_view->setTitle('Changes Saved');
        $error_view->appendChild('<p>Your changes have been saved.</p>');
        $error_view = $error_view->render();
      }
    }

    $img_src = PhabricatorFileURI::getViewURIForPHID(
      $user->getProfileImagePHID());
    $profile_uri = PhabricatorEnv::getURI('/p/'.$user->getUsername().'/');

    $form = new AphrontFormView();
    $form
      ->setUser($request->getUser())
      ->setAction('/settings/page/profile/')
      ->setEncType('multipart/form-data')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Title')
          ->setName('title')
          ->setValue($profile->getTitle())
          ->setCaption('Serious business title.'))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel('Profile URI')
          ->setValue(
            phutil_render_tag(
              'a',
              array(
                'href' => $profile_uri,
              ),
              phutil_escape_html($profile_uri))))
      ->appendChild(
        '<p class="aphront-form-instructions">Write something about yourself! '.
        'Make sure to include <strong>important information</strong> like '.
        'your favorite pokemon and which Starcraft race you play.</p>')
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Blurb')
          ->setName('blurb')
          ->setValue($profile->getBlurb()))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel('Profile Image')
          ->setValue(
            phutil_render_tag(
              'img',
              array(
                'src' => $img_src,
              ))))
      ->appendChild(
        id(new AphrontFormFileControl())
          ->setLabel('Change Image')
          ->setName('image'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save')
          ->addCancelButton('/p/'.$user->getUsername().'/'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Edit Profile Details');
    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    return id(new AphrontNullView())
      ->appendChild(
        array(
          $error_view,
          $panel,
        ));
  }

}
