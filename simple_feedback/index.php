<?php

/**
 * This plugin adds a small button to the footer of the page that
 * when clicked opens a small area where the user can report feedback
 * which will be emailed to the config email.
 */
class Plugin_simple_feedback extends Plugins {
    
    private static $feedbackButton = '';
    
    public function __construct() {
        
        self::setPluginSettings('position', 'default');
        self::setPluginSettings('service', 'mailgun');
        
        // Add styles
        self::setPluginSettings('styles', array(
            'css/styles.css'
        ));
        
        // Add scripts
        self::setPluginSettings('scripts', array(
            '//code.jquery.com/jquery-1.11.0.min.js',
            'js/script.js'
        ));
        
        $html = '<!-- Begin Simple Feedback Wrap --><div class="' . self::prefix('wrapper') . ' ' . self::prefix('position-' . self::getPluginSettings('position')) . '">';
        
            $html .= '<div class="' . self::prefix('button') . '">' . self::_('Feedback') . '</div>';
            $html .= '<form action="' . self::getPluginRequestUrl('json', 'feedbackSubmitted') . '" class="' . self::prefix('content') . '">';
        
                $html .= '<h2>' . self::_('We appreciate all feedback!') . '</h2>';
                
                $html .= '<div class="' . self::prefix('message') . ' ' . self::prefix('success') . '">' . self::_('Your feedback was successfully submitted') . '</div>';
                $html .= '<div class="' . self::prefix('message') . ' ' . self::prefix('error') . '"></div>';
        
                $html .= '<label for="' . self::prefix('form-type') . '">' . self::_('Type') . '</label>';
                $html .= '<select id="' . self::prefix('form-type') . '" name="' . self::inputName('type') . '">';
                    $html .= '<option value="issue">' . self::_('Report Issue') . '</option>';
                    $html .= '<option value="feature">' . self::_('Feature Request') . '</option>';
                    $html .= '<option value="general">' . self::_('General') . '</option>';
                $html .= '</select>';
        
                $html .= '<label for="' . self::prefix('form-message') . '">' . self::_('Message') . '</label>';
                $html .= '<textarea id="' . self::prefix('form-message') . '" name="' . self::inputName('message') . '" placeholder="' . self::_('Your message...') . '"></textarea>';
                
                $html .= '<input type="submit" value="' . self::_('Submit') . '">';
        
            $html .= '</form>';
        
        $html .= '</div><!-- End Simple Feedback Wrap -->';
        
        self::$feedbackButton = $html;
        
    }
    
    public static function _json($data) {
        
        // Verify we have a type and message
        if(empty($data['type']) || empty($data['message'])) {
            self::assign('error', self::_('Type and message are both required.'));
            return;
        }
        
        self::sendFeedback($data);
        
    }
    
    private static function sendFeedback($data) {
        
        $html  = '<h1>' . sprintf(self::_('Feedback Submitted From %s'), SEF::getBaseUrl()) . '</h2>';
        $html .= '<h2>' . self::_(ucfirst($data['type'])) . '</h2>';
        $html .= '<p>' . self::_(ucfirst($data['message'])) . '</p>';
        
        $data = array(
            'from' => self::getPluginSettings('emailFrom'),
            'to'   => self::getPluginSettings('emailTo'),
            'subject' => self::_('Feedback Submission From ' . SEF::getBaseUrl()),
            'html' => $html
        );

        $ch = curl_init('https://api.mailgun.net/v2/' . self::getPluginSettings('mailgunUrl') . '/messages');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, 'api:' . self::getPluginSettings('mailgunAPIKey'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_exec($ch);
        
        if($error = curl_error($ch)) {
            self::assign('error', $error);
        }
        
    }
    
    public static function onViewGetFooter($content) {
        return $content . self::$feedbackButton;
    }
}

?>