<?php
/**
 * Tatoeba Project, free collaborative creation of multilingual corpuses project
 * Copyright (C) 2009  HO Ngoc Phuong Trang <tranglich@gmail.com>
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
 * @author   HO Ngoc Phuong Trang <tranglich@gmail.com>
 * @license  Affero General Public License
 * @link     http://tatoeba.org
 */
 
/**
 * Display a message of the Wall.
 *
 * @category Wall
 * @package  Views
 * @author   HO Ngoc Phuong Trang <tranglich@gmail.com>
 * @license  Affero General Public License
 * @link     http://tatoeba.org
 */ 
?>
<div id="annexe_content">
    <div class="module">
    <h2>Menu</h2>
    <?php
        echo '<p>';
        echo $html->link(
            __('Back to Wall', true),
            array(
                'controller' => 'wall',
                'action' => 'index',
                'paginated'
            )
        );
        echo '</p>';
        
        echo '<p>';        
        echo $html->link(
            'Show message in its context',
            array(
                'controller' => 'wall',
                'action' => 'index#message_'.$message['Wall']['id']
            )
        );
        echo '</p>';
    ?>
    </div>
    
    <div class="module">
    <h2>Warning</h2>
    <p>
    This message is displayed out of context. Yes, we know it would be more 
    practical if you could see the whole thread instead of the message alone. 
    Please wait until April... In the meantime you can click on this:
    </p>
    
    <p>
    <?php
    echo $html->link(
        'Show message in its context',
        array(
            'controller' => 'wall',
            'action' => 'index#message_'.$message['Wall']['id']
        )
    );
    ?>
    </div>
</div>

<div id="main_content">
    
    <div class="module" style="display:none">
        <?php
        // Users are not suppoed to the able to post new message from here,
        // but we need the form so that the Javascript works properly.
        if ($isAuthenticated) {
            echo '<div id="sendMessageForm">'."\n";
            echo $wall->displayAddMessageToWallForm();
            echo '</div>'."\n";
        }
        ?>
    </div>
    
    <div class="module">    
        <ol class="wall">
        <div class="topThread" 
            id="messageBody_<?php echo $message['Wall']['id']; ?>">
            <ul>
                <?php
                $wall->createRootDiv(
                    $message['Wall'],
                    $message['User'],
                    $messagePermissions
                );
                ?>
            <ul>
        </div>
        </ol>
    </div>
    
</div>