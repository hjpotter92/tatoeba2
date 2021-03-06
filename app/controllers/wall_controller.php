<?php
/**
 * Tatoeba Project, free collaborative creation of multilingual corpuses project
 * Copyright (C) 2009  Allan SIMON <allan.simon@supinfo.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 *
 * @category PHP
 * @package  Tatoeba
 * @author   Allan SIMON <allan.simon@supinfo.com>
 * @license  Affero General Public License
 * @link     http://tatoeba.org
 */

/**
 * Controller for the wall.
 *
 * @category Wall
 * @package  Controllers
 * @author   Allan SIMON <allan.simon@supinfo.com>
 * @license  Affero General Public License
 * @link     http://tatoeba.org
 */

class WallController extends Appcontroller
{
    
    public $persistentModel = true;

    public $name = 'Wall' ;
    public $paginate = array(
        "order" => "WallThread.last_message_date DESC", 
        "limit" => 10,
        "fields" => array ("lft",'rght'), 
        "conditions" => array (
            "Wall.parent_id" => null ,
        ),
        "contain" => array (
            "WallThread" => array(
                'fields' => "last_message_date"
            )
        )
    );
    public $helpers = array(
        'Wall',
        'Javascript',
        'Date',
        'Pagination',
        'AttentionPlease'
    );
    public $components = array ('Mailer');
    /**
     * to know who can do what
     *
     * @return void
     */

    public function beforeFilter()
    {
        parent::beforeFilter();
        // TODO set correct right
        $this->Auth->allowedActions = array(
            'index',
            'show_message',
            'messages_of_user',
            // The actions below should not be allowed here, but I don't feel like
            // taking the time to update the ACL stuff. I'M SORRY!
            'hide_message',
            'unhide_message',
            'edit'
        );
    }

    /**
     * display main wall page with all messages
     * TODO need to paginate it
     *
     * @return void
     */
    public function index()
    {
        
        //$messages = $this->_organize_messages($messages);
        $tenLastMessages = $this->Wall->getLastMessages(10);
        //pr($messages);
        $userId = $this->Auth->user('id');
        $groupId = $this->Auth->user('group_id');

        $messagesIdDirty = $this->paginate();
        $messageLftRght = array();
        foreach ($messagesIdDirty as $messageDirty) {
            array_push($messageLftRght, $messageDirty['Wall']);
        }
        
        $messages = $this->Wall->getMessagesThreaded($messageLftRght);

        
        $messages = $this->Permissions->getWallMessagesOptions(
            $messages,
            $userId,
            $groupId
        );
         
        $isAuthenticated = !empty($userId);

        //pr($messagesPermissions);
        $this->set('isAuthenticated', $isAuthenticated); 
        //$this->set('messagesPermissions', $messagesPermissions); 
        $this->set('allMessages', $messages);
        $this->set('tenLastMessages', $tenLastMessages);
        //$this->set('firstMessages', $firstMessages);
        

    }

    /**
     * use to organize the messages array the following way
     * message_id => message, we need it as deleted message
     * will shift the index
     * 
     * @param array $messages The messages array to organize
     *
     * @return array
     */

    private function _organize_messages($messages)
    {
        $newMessages = array();
        foreach ($messages as $message) {
            $newMessages[$message['Wall']['id']] = $message;
        }

        return $newMessages;
    }
    
    /**
     * save a new first message
     *
     * @return void
     */
    public function save()
    {
        if (!empty($this->data['Wall']['content'])
            && $this->Auth->user('id')
        ) {
            $now = date("Y-m-d H:i:s");  

            $this->data['Wall']['owner'] = $this->Auth->user('id');
            $this->data['Wall']['date'] = $now; 

            $lastMess = $this->Cookie->read('hash_last_wall');
            $lastMess = $this->Session->read('hash_last_wall');
            $thisMess =md5($this->data['Wall']['content']);
            
            $this->Session->write(
                'hash_last_wall',
                $thisMess
            );
            $this->Cookie->write(
                'hash_last_wall',
                $thisMess,
                false,
                "+1 month"
            );
            if ($lastMess !=$thisMess ) {
                
                    
                // now save to database 
                if ($this->Wall->save($this->data)) {
                    $this->update_thread_date(
                        $this->Wall->id,
                        $now
                    );
                }

            }
        }

        $this->redirect(
            array('action'=>'index')
        );
    }

    /**
     * save a new reply
     *
     * @return void
     */ 
   
    public function save_inside()
    {
        
        $idTemp = $this->Auth->user('id');
        if (isset($_POST['content'])
            && trim($_POST['content']) != ''
            && isset($_POST['replyTo'])
            && !(empty($idTemp))
        ) {

            $content = Sanitize::stripScripts($_POST['content']);
            $parentId = Sanitize::paranoid($_POST['replyTo']);
            $now = date("Y-m-d H:i:s"); 
            
            $this->data['Wall']['content'] = $content ; 
            $this->data['Wall']['owner'] = $idTemp ;
            $this->data['Wall']['parent_id'] = $parentId ;
            $this->data['Wall']['date'] = $now;
            // now save to database 
            if ($this->Wall->save($this->data)) {
                $newMessageId = $this->Wall->id ;
                
                $this->loadModel('User');
                $user = $this->User->getInfoWallUser($idTemp);
                $this->set("user", $user); 
               
                $this->update_thread_date($newMessageId, $now);
                
                // we forge a message to be used in the view
                
                $message['Wall']['content'] = $content; 
                $message['Wall']['owner'] = $idTemp;
                $message['Wall']['parent_id'] = $parentId;
                $message['Wall']['date'] = $now;
                $message['Wall']['id'] = $newMessageId; 
                 
                $message['User']['image'] = $user['User']['image'];
                if (empty($message['User']['image'])) {
                    $message['User']['image'] = 'unknown-avatar.png';
                }

                $message['User']['username'] = $user['User']['username'];

                $this->set("message", $message); 
                
                // ------------------
                // send notification
                // ------------------
                
                // Retrieve parent message
                $parentMessage = $this->Wall->getMessageForMail($parentId);
                
                // prepare email
                // TODO : i18n mail + move this out of here.
                if ($parentMessage['User']['send_notifications']
                    && $parentMessage['User']['id'] != $idTemp 
                ) {
                    $participant = $parentMessage['User']['email'];
                    $subject  = 'Tatoeba - ' .
                         $message['User']['username'] .
                         ' has replied to you on the Wall';
                    
                    $mailContent 
                        = 'http://'.$_SERVER['HTTP_HOST'].'/wall/show_message/'
                        .$message['Wall']['id'].'#message_'.$message['Wall']['id']
                        ."\n\n";
                    $mailContent .= '- - - - - - - - - - - - - - - - -'."\n\n";
                    $mailContent .= $message['Wall']['content']."\n\n";
                    $mailContent .= '- - - - - - - - - - - - - - - - -'."\n\n";
                    
                    $this->Mailer->to = $participant;
                    $this->Mailer->toName = '';
                    $this->Mailer->subject = $subject;
                    $this->Mailer->message = $mailContent;
                    $this->Mailer->send();
                }
            }
        }
    }

    /**
     * Edit a wall post
     * 
     * @param int $messageId Id of the message to edit
     * 
     * @return void
     */
    public function edit($messageId)
    {
        $this->log(print_r($this->data, true), "DEBUG2");
        $messageId = Sanitize::paranoid($messageId);
        $this->Wall->id = $messageId;
        
        if (empty($this->data)) {
            $message = $this->Wall->read();
            $this->data = $message;
            
            $messageOwnerId = $this->Wall->getOwnerIdOfMessage($messageId);
            $messagePermissions = $this->Permissions->getWallMessageOptions(
                null,
                $messageOwnerId,
                CurrentUser::get('id'),
                CurrentUser::get('group_id')
            );
            
            if ($messagePermissions['canEdit'] == false) {
                $this->Session->setFlash(
                    __("You do not have permission to edit this message. ", true).
                    __(
                        "If you have received this message wrongly, ".
                        "please contact administrators at ".
                        "team@tatoeba.org.", true
                    )
                );
                $this->redirect(
                    array(
                        "action"=>"index",
                    )
                );
            } else {
                $this->set("message", $message);
            }
        } else {
            //$this->data is not empty, so go save
            $messageId = $this->data['Wall']['id'];
            $this->Wall->id = $messageId;
            
            $messageOwnerId = $this->Wall->getOwnerIdOfMessage($messageId);
            $messagePermissions = $this->Permissions->getWallMessageOptions(
                null,
                $messageOwnerId,
                CurrentUser::get('id'),
                CurrentUser::get('group_id')
            );
            if ($messagePermissions['canEdit'] == false) {
                $this->Session->setFlash(
                    __("You do not have permission to edit this message.", true).
                    __(
                        "If you have received this message wrongly, ".
                        "please contact administrators at ".
                        "team@tatoeba.org.", true
                    )
                );
                $this->redirect(
                    array(
                        "action"=>"index",
                    )
                );
            } else {
                if ($this->Wall->save($this->data)) {
                    $this->Session->setFlash(
                        __("Message saved.", true)
                    );
                    $this->redirect(
                        array(
                            "action"=>"index",
                            "$messageId#message_$messageId"
                        )
                    );
                } else {
                    $this->Session->setFlash(
                        __(
                            "We apologize, but we could not save your data.
                             Please try again", true
                        )
                    );
                    $this->redirect(
                        array(
                            "action"=>"edit",
                            $messageId
                        )
                    );
                }
            }
        
        }
    
    }
    
    /**
     * use to delete a given message on the wall
     *
     * @param int $messageId Id of the message to delete
     *
     * @return void
     */

    public function delete_message($messageId)
    {
        $messageId = Sanitize::paranoid($messageId);
        if ($this->Wall->hasMessageReplies($messageId)) {
            // if the message has replies then we can't delete it
            // redirect to previous page
            $this->redirect($this->referer()); 
        }
        $messageOwnerId = $this->Wall->getOwnerIdOfMessage($messageId);
        //we check a second time even if it has been checked while displaying
        // or not the delete icon, but one can try to directly call delete_message
        // so we need to recheck
        $messagePermissions = $this->Permissions->getWallMessageOptions(
            null,
            $messageOwnerId,
            $this->Auth->user('id'),
            $this->Auth->user('group_id')
        );
        if ($messagePermissions['canDelete']) {
            // second parameter "true" => delete in cascade
            $this->Wall->delete($messageId, true);
        }
        // redirect to previous page
        $this->redirect($this->referer()); 
    }

    /**
     * update the WallThread table
     * 
     * @param int  $messageId Message that have been add/updated
     * @param date $newDate   Date of the event.
     *
     * @return void
     */

    public function update_thread_date($messageId, $newDate)
    {
        $messageId = Sanitize::paranoid($messageId);
        // TODO Not sure what to do with $newDate...
        
        $this->loadModel('WallThread');

        $rootId = $this->Wall->getRootMessageIdOfReply($messageId);
        
        $newThreadData = array(
            'id' => $rootId,
            'last_message_date' => $newDate
        );
        
        $this->WallThread->save($newThreadData);
    }

    /**
     * Use to display a single thread
     * usefull when we want a permalink to a message with its whole thread
     *
     * @param int $messageId the message to display with its thread
     *
     * @return void
     */
    public function show_message($messageId)
    {
        $messageId = Sanitize::paranoid($messageId);
        
        $userId = $this->Auth->user('id');
        $groupId = $this->Auth->user('group_id');

        $thread = $this->Wall->getWholeThreadContaining($messageId);

        /* NOTE : have a link to point the thread within the other thread
           is virtually impossible, as the ordering can change between the page
           generation and the user click on the link
        */


        $thread = $this->Permissions->getWallMessagesOptions(
            $thread,
            $userId,
            $groupId
        );
        $this->set("message", $thread[0]);
        $this->set("isAuthenticated", $this->Auth->user());
    }
    
    
    /**
     * Display messages of a user.
     *
     * @param string $username Username.
     *
     * @return void
     */
    public function messages_of_user($username)
    {
        $userId = $this->Wall->User->getIdFromUsername($username);
        
        $this->paginate = array(
            "order" => "date DESC", 
            "limit" => 20,
            "fields" => array ("id", "date", "content", "hidden", "owner"), 
            "conditions" => array (
                "owner" => $userId,
            ),
            "contain" => array (
                "User" => array(
                    'fields' => array("username", "image")
                )
            )
        );
        
        $messages = $this->paginate();
        
        $this->set("messages", $messages);
        $this->set("username", $username);
    }
    
    
    /**
     * Hides a given message on the Wall. The message is still going to be there
     * but only visible to the admins and the author of the message.
     *
     * @param int $messageId Id of the message to hide
     *
     * @return void
     */
    public function hide_message($messageId)
    {
        if (CurrentUser::isAdmin()) {
            $messageId = Sanitize::paranoid($messageId);
            
            $this->Wall->id = $messageId;
            $this->Wall->saveField('hidden', true);
            
            // redirect to previous page
            $this->redirect($this->referer()); 
        }

    }
    
    
    /**
     * Display back a given message on the Wall that was hidden.
     *
     * @param int $messageId Id of the message to display again
     *
     * @return void
     */
    public function unhide_message($messageId)
    {
        if (CurrentUser::isAdmin()) {
            $messageId = Sanitize::paranoid($messageId);
            
            $this->Wall->id = $messageId;
            $this->Wall->saveField('hidden', false);
            
            // redirect to previous page
            $this->redirect($this->referer()); 
        }

    }
    
}


?>
