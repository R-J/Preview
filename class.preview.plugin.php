<?php if (!defined('APPLICATION')) exit();

$PluginInfo['Preview'] = array(
   'Name' => 'Preview',
   'Description' => 'Adds possibility to preview discussion and last comment of a discussion in a popup.',
   'Version' => '0.1',
   'RequiredApplications' => array('Vanilla' => '2.1'),
   'MobileFriendly' => TRUE,
   'RegisterPermissions' => array('Plugins.Preview.View'),
   'SettingsPermission' => 'Garden.Settings.Manage',
   'SettingsUrl' => '/settings/preview',
   'Author' => 'Robin Jurinka',
   'License' => 'MIT'
);

class PreviewPlugin extends Gdn_Plugin {
   /**
    * Passes $Sender to $this->InsertDiscussionOption
    *
    * @param object $Sender DiscussionsController
    */
   public function DiscussionsController_DiscussionOptions_Handler($Sender) {
      $this->InsertDiscussionOption($Sender);
   }

   /**
    * Passes $Sender to $this->InsertDiscussionOption
    *
    * @param object $Sender CategoriesController
    */
   public function CategoriesController_DiscussionOptions_Handler($Sender) {
      $this->InsertDiscussionOption($Sender);
   }

   /**
    * Inserts a menu item to the discussion options for roles with
    * appropriate permission
    *
    * @param object $Sender either DiscussionsController or CategoriesController
    */
   private function InsertDiscussionOption($Sender) {
      // check for plugins permission
      if (!CheckPermission('Plugins.Preview.View')) {
         return;
      }
      $Discussion = $Sender->EventArguments['Discussion'];
      $DiscussionID = $Discussion->DiscussionID;
      $Sender->Options .= '<li>'.Anchor(T('Preview'), '/dashboard/plugin/preview/'.$DiscussionID, 'Popup') . '</li>';
   }

   /**
    * Shows discussion and last comment in a popup using original Vanilla views
    * Posts could be truncated at a configurable length
    *
    * @param object $Sender PluginController
    * @param array  $Args   $Args[0] is DiscussionID
    */
   public function PluginController_Preview_Create($Sender, $Args) {
      // check for plugins permission
      $Sender->Permission('Plugins.Preview.View');

      $DiscussionID = $Args[0];
      $DiscussionModel = new DiscussionModel();
      $Discussion = $DiscussionModel->GetID($DiscussionID);

      // check for category permission
      $Sender->Permission('Vanilla.Discussions.View', TRUE, 'Category', $Discussion->PermissionCategoryID);

      // truncate discussion if applicable
      $MaxDiscussionLength = C('Plugins.Preview.MaxDiscussionLength', 160);
      if (strlen($Discussion->Body) > $MaxDiscussionLength && $MaxDiscussionLength != 0) {
         $Discussion->Body = substr($Discussion->Body, 0, $MaxDiscussionLength).Anchor('[...]', DiscussionUrl($Discussion));
      }
      // retrieve and render discussion view
      $Sender->SetData('Discussion', $Discussion);
      require_once $Sender->FetchViewLocation('helper_functions', 'Discussion', 'Vanilla');
      $Sender->Render($Sender->FetchViewLocation('discussion', 'Discussion', 'Vanilla'));

      // get last comment
      $CommentModel = new CommentModel();
      $Comment = $CommentModel->GetID($Discussion->LastCommentID);
      if($Comment) {
         $Session = Gdn::Session();
         // truncate comment if applicable
         $MaxCommentLength = C('Plugins.Preview.MaxCommentLength', 160);
         if (strlen($Comment->Body) > $MaxCommentLength && $MaxCommentLength != 0) {
            $Comment->Body = substr($Comment->Body, 0, $MaxCommentLength).Anchor('[...]', CommentUrl($Comment));
         }
         // write last comment
         echo '<hr />';
         echo '<ul class="MessageList DataList Comments">';
            WriteComment($Comment, $this, $Session, 1);
         echo '</ul>';
      }
   }


   /**
    * Create a very simple settings screen
    *
    * @param object $Sender SettingsController
    */
   public function SettingsController_Preview_Create($Sender) {
      $Sender->Permission('Garden.Settings.Manage');
      $Sender->SetData('Title', T('Preview Settings'));
      $Sender->AddSideMenu('dashboard/settings/plugins');
      $ConfigurationModule = new ConfigurationModule($Sender);
      $ConfigurationModule->Initialize(array(
         'Plugins.Preview.MaxDiscussionLength',
         'Plugins.Preview.MaxCommentLength'
      ));
      $Schema = array(
         'Plugins.Preview.MaxDiscussionLength' => array(
            'LabelCode' => 'Maximum characters to show in a discussion<br />(0 for no limit)',
            'Control' => 'TextBox',
            'Default' => C('Plugins.Preview.MaxDiscussionLength', '160')
         ),
         'Plugins.Preview.MaxCommentLength' => array(
            'LabelCode' => 'Maximum characters to show in a comment<br />(0 for no limit)',
            'Control' => 'TextBox',
            'Default' => C('Plugins.Preview.MaxCommentLength', '160')
         )
      );
      $ConfigurationModule->Schema($Schema);
      $ConfigurationModule->RenderAll();
   }
}
