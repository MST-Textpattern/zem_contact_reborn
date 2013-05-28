<?php

function zem_contact($atts, $thing = null)
{
    global $sitename, $prefs, $production_status, $zem_contact_from,
        $zem_contact_recipient, $zem_contact_error, $zem_contact_submit,
        $zem_contact_form, $zem_contact_labels, $zem_contact_values;

    extract(lAtts(array(
        'copysender'   => 0,
        'form'         => '',
        'from'         => '',
        'label'        => gTxt('zem_contact_reborn_contact'),
        'redirect'     => '',
        'show_error'   => 1,
        'show_input'   => 1,
        'send_article' => 0,
        'subject'      => gTxt('zem_contact_reborn_email_subject', array('{sitename}' => $sitename), false),
        'to'           => '',
        'thanks'       => graf(gTxt('zem_contact_reborn_email_thanks')),
        'thanks_form'  => '',
    ), $atts));

    unset($atts['show_error'], $atts['show_input']);
    $zem_contact_form_id = md5(serialize($atts).preg_replace('/[\t\s\r\n]/','',$thing));
    $zem_contact_submit = (ps('zem_contact_form_id') == $zem_contact_form_id);

    if (headers_sent() === false)
    {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s',time()-3600*24*7).' GMT');
        header('Expires: '.gmdate('D, d M Y H:i:s',time()+600).' GMT');
        header('Cache-Control: no-cache, must-revalidate');
    }

    $nonce   = doSlash(ps('zem_contact_nonce'));
    $renonce = false;

    if ($zem_contact_submit)
    {
        safe_delete('txp_discuss_nonce', 'issue_time < date_sub(now(), interval 10 minute)');
        if ($rs = safe_row('used', 'txp_discuss_nonce', "nonce = '$nonce'"))
        {
            if ($rs['used'])
            {
                unset($zem_contact_error);
                $zem_contact_error[] = gTxt('zem_contact_reborn_form_used');
                $renonce = true;
                $_POST = array();
                $_POST['zem_contact_submit'] = TRUE;
                $_POST['zem_contact_form_id'] = $zem_contact_form_id;
                $_POST['zem_contact_nonce'] = $nonce;
            }
        }
        else
        {
            $zem_contact_error[] = gTxt('zem_contact_reborn_form_expired');
            $renonce = true;
        }
    }

    if ($zem_contact_submit and $nonce and !$renonce)
    {
        $zem_contact_nonce = $nonce;
    }

    elseif (!$show_error or $show_input)
    {
        $zem_contact_nonce = md5(uniqid(rand(), true));
        safe_insert('txp_discuss_nonce', "issue_time = now(), nonce = '$zem_contact_nonce'");
    }

    if ($thing === null)
    {
        $form = parse_form($form);
    }
    else
    {
        $form = parse($thing);
    }

    if (!$to and !$send_article)
    {
        return gTxt('zem_contact_reborn_to_missing');
    }

    $out = '';

    if (!$zem_contact_submit) {
      # don't show errors or send mail
    }

    elseif (!empty($zem_contact_error))
    {
        if ($show_error or !$show_input)
        {
            $out .= n.'<ul class="zemError">';

            foreach (array_unique($zem_contact_error) as $error)
            {
                $out .= n.t.'<li>'.$error.'</li>';
            }

            $out .= n.'</ul>';

            if (!$show_input) return $out;
        }
    }

    elseif ($show_input and is_array($zem_contact_form))
    {
        /// load and check spam plugins/
        callback_event('zemcontact.submit');
        $evaluation =& get_zemcontact_evaluator();
        $clean = $evaluation->get_zemcontact_status();
        if ($clean != 0) {
            return gTxt('zem_contact_reborn_spam');
        }

        $sep = !is_windows() ? "\n" : "\r\n";

        $msg = array();

        foreach ($zem_contact_labels as $name => $label)
        {
            if (!trim($zem_contact_values[$name])) continue;
            $msg[] = $label.': '.$zem_contact_values[$name];
        }

        if ($send_article)
        {
            global $thisarticle;
            $subject = str_replace('&#38;', '&', $thisarticle['title']);
            $msg[] = permlinkurl($thisarticle);
            $msg[] = $subject;
            $s_ar = array('&#8216;', '&#8217;', '&#8220;', '&#8221;', '&#8217;', '&#8242;', '&#8243;', '&#8230;', '&#8211;', '&#8212;', '&#215;', '&#8482;', '&#174;', '&#169;', '&lt;', '&gt;', '&quot;', '&amp;', '&#38;', "\t", '<p');
            if ($prefs['override_emailcharset'] and is_callable('utf8_decode')) {
                $r_ar = array("'", "'", '"', '"', "'", "'", '"', '...', '-', '--', 'x', '[tm]', '(r)', '(c)', '<', '>', '"', '&', '&', ' ', "\n<p");
            }
            else
            {
                $r_ar = array('‘', '’', '“', '”', '’', '′', '″', '…', '–', '—', '×', '™', '®', '©', '<', '>', '"', '&', '&', ' ', "\n<p");
            }
            $msg[] = trim(strip_tags(str_replace($s_ar,$r_ar,(trim(strip_tags($thisarticle['excerpt'])) ? $thisarticle['excerpt'] : $thisarticle['body']))));
            if (empty($zem_contact_recipient))
            {
                return gTxt('zem_contact_reborn_field_missing', array(
                    '{recipient}' => gTxt('zem_contact_reborn_recipient'),
                ), false);
            }
            else
            {
                $to = $zem_contact_recipient;
            }
        }

        $msg = join("\n\n", $msg);
        $msg = str_replace("\r\n", "\n", $msg);
        $msg = str_replace("\r", "\n", $msg);
        $msg = str_replace("\n", $sep, $msg);

        if ($from)
        {
            $reply = $zem_contact_from;
        }

        else
        {
            $from = $zem_contact_from;
            $reply = '';
        }

        $from    = zem_contact_strip($from);
        $to      = zem_contact_strip($to);
        $subject = zem_contact_strip($subject);
        $reply   = zem_contact_strip($reply);
        $msg     = zem_contact_strip($msg, FALSE);

        if ($prefs['override_emailcharset'] and is_callable('utf8_decode'))
        {
            $charset = 'ISO-8859-1';
            $subject = utf8_decode($subject);
            $msg     = utf8_decode($msg);
        }

        else
        {
            $charset = 'UTF-8';
        }

        $subject = zem_contact_mailheader($subject, 'text');

        $headers = 'From: '.$from.
            ($reply ? ($sep.'Reply-To: '.$reply) : '').
            $sep.'X-Mailer: Textpattern (zem_contact_reborn)'.
            $sep.'X-Originating-IP: '.zem_contact_strip((!empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'].' via ' : '').$_SERVER['REMOTE_ADDR']).
            $sep.'Content-Transfer-Encoding: 8bit'.
            $sep.'Content-Type: text/plain; charset="'.$charset.'"';

        safe_update('txp_discuss_nonce', "used = '1', issue_time = now()", "nonce = '$nonce'");

        if (mail($to, $subject, $msg, $headers))
        {
            $_POST = array();

            if ($copysender and $zem_contact_from)
            {
                mail(zem_contact_strip($zem_contact_from), $subject, $msg, $headers);
            }

            if ($redirect)
            {
                while (@ob_end_clean());
                $uri = hu.ltrim($redirect,'/');
                if (empty($_SERVER['FCGI_ROLE']) and empty($_ENV['FCGI_ROLE']))
                {
                    txp_status_header('303 See Other');
                    header('Location: '.$uri);
                    header('Connection: close');
                    header('Content-Length: 0');
                }
                else
                {
                    $uri = htmlspecialchars($uri);
                    $refresh = gTxt('zem_contact_reborn_refresh');
                    echo <<<END
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    <title>$sitename</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta http-equiv="refresh" content="0;url=$uri" />
</head>
<body>
<a href="$uri">$refresh</a>
</body>
</html>
END;
                }
                exit;
            }

            else
            {
                return '<div class="zemThanks" id="zcr'.$zem_contact_form_id.'">' .
                    ($thanks_form ? fetch_form($thanks_form) : $thanks) .
                    '</div>';
            }
        }

        else
        {
            $out .= graf(gTxt('zem_contact_reborn_mail_sorry'));
        }
    }

    if ($show_input and !$send_article or gps('zem_contact_send_article'))
    {
        return '<form method="post"'.((!$show_error and $zem_contact_error) ? '' : ' id="zcr'.$zem_contact_form_id.'"').' class="zemContactForm" action="'.htmlspecialchars(serverSet('REQUEST_URI')).'#zcr'.$zem_contact_form_id.'">'.
            ( $label ? n.'<fieldset>' : n.'<div>' ).
            ( $label ? n.'<legend>'.htmlspecialchars($label).'</legend>' : '' ).
            $out.
            n.'<input type="hidden" name="zem_contact_nonce" value="'.$zem_contact_nonce.'" />'.
            n.'<input type="hidden" name="zem_contact_form_id" value="'.$zem_contact_form_id.'" />'.
            $form.
            callback_event('zemcontact.form').
            ( $label ? (n.'</fieldset>') : (n.'</div>') ).
            n.'</form>';
    }

    return '';
}

function zem_contact_strip($str, $header = TRUE) {
    if ($header) $str = strip_rn($str);
    return preg_replace('/[\x00]/', ' ', $str);
}

function zem_contact_text($atts)
{
    global $zem_contact_error, $zem_contact_submit;

    extract(lAtts(array(
        'break'        => br,
        'default'    => '',
        'isError'    => '',
        'label'        => gTxt('zem_contact_reborn_text'),
        'max'        => 100,
        'min'        => 0,
        'name'        => '',
        'required'    => 1,
        'size'        => ''
    ), $atts));

    $min = intval($min);
    $max = intval($max);
    $size = intval($size);

    if (empty($name)) $name = zem_contact_label2name($label);

    if ($zem_contact_submit)
    {
        $value = trim(ps($name));
        $utf8len = preg_match_all("/./su", $value, $utf8ar);
        $hlabel = htmlspecialchars($label);

        if (strlen($value))
        {
            if (!$utf8len)
            {
                $zem_contact_error[] = gTxt('zem_contact_reborn_invalid_utf8', $hlabel);
                $isError = "errorElement";
            }

            elseif ($min and $utf8len < $min)
            {
                $zem_contact_error[] = gTxt('zem_contact_reborn_min_warning', $hlabel, $min);
                $isError = "errorElement";
            }

            elseif ($max and $utf8len > $max)
            {
                $zem_contact_error[] = gTxt('zem_contact_reborn_max_warning', $hlabel, $max);
                $isError = "errorElement";
                #$value = join('',array_slice($ar[0],0,$max));
            }

            else
            {
                zem_contact_store($name, $label, $value);
            }
        }
        elseif ($required)
        {
            $zem_contact_error[] = gTxt('zem_contact_reborn_field_missing', $hlabel);
            $isError = "errorElement";
        }
    }

    else
    {
        $value = $default;
    }

    $size = ($size) ? ' size="'.$size.'"' : '';
    $maxlength = ($max) ? ' maxlength="'.$max.'"' : '';

    $zemRequired = $required ? 'zemRequired' : '';

        return '<label for="'.$name.'" class="zemText '.$zemRequired.$isError.' '.$name.'">'.htmlspecialchars($label).'</label>'.$break.
        '<input type="text" id="'.$name.'" class="zemText '.$zemRequired.$isError.'" name="'.$name.'" value="'.htmlspecialchars($value).'"'.$size.$maxlength.' />';
}

function zem_contact_textarea($atts)
{
    global $zem_contact_error, $zem_contact_submit;

    extract(lAtts(array(
        'break'        => br,
        'cols'        => 58,
        'default'    => '',
        'isError'    => '',
        'label'        => gTxt('zem_contact_reborn_message'),
        'max'        => 10000,
        'min'        => 0,
        'name'        => '',
        'required'    => 1,
        'rows'        => 8
    ), $atts));

    $min = intval($min);
    $max = intval($max);
    $cols = intval($cols);
    $rows = intval($rows);

    if (empty($name)) $name = zem_contact_label2name($label);

    if ($zem_contact_submit)
    {
        $value = preg_replace('/^\s*[\r\n]/', '', rtrim(ps($name)));
        $utf8len = preg_match_all("/./su", ltrim($value), $utf8ar);
        $hlabel = htmlspecialchars($label);

        if (strlen(ltrim($value)))
        {
            if (!$utf8len)
            {
                $zem_contact_error[] = gTxt('zem_contact_reborn_invalid_utf8', $hlabel);
                $isError = "errorElement";
            }

            elseif ($min and $utf8len < $min)
            {
                $zem_contact_error[] = gTxt('zem_contact_reborn_min_warning', $hlabel, $min);
                $isError = "errorElement";
            }

            elseif ($max and $utf8len > $max)
            {
                $zem_contact_error[] = gTxt('zem_contact_reborn_max_warning', $hlabel, $max);
                $isError = "errorElement";
                #$value = join('',array_slice($utf8ar[0],0,$max));
            }

            else
            {
                zem_contact_store($name, $label, $value);
            }
        }

        elseif ($required)
        {
            $zem_contact_error[] = gTxt('zem_contact_reborn_field_missing', $hlabel);
            $isError = "errorElement";
        }
    }

    else
    {
        $value = $default;
    }

    $zemRequired = $required ? 'zemRequired' : '';

    return '<label for="'.$name.'" class="zemTextarea '.$zemRequired.$isError.' '.$name.'">'.htmlspecialchars($label).'</label>'.$break.
        '<textarea id="'.$name.'" class="zemTextarea '.$zemRequired.$isError.'" name="'.$name.'" cols="'.$cols.'" rows="'.$rows.'">'.htmlspecialchars($value).'</textarea>';
}

function zem_contact_email($atts)
{
    global $zem_contact_error, $zem_contact_submit, $zem_contact_from, $zem_contact_recipient;

    extract(lAtts(array(
        'default'    => '',
        'isError'    => '',
        'label'        => gTxt('zem_contact_reborn_email'),
        'max'        => 100,
        'min'        => 0,
        'name'        => '',
        'required'    => 1,
        'break'        => br,
        'size'        => '',
        'send_article'    => 0
    ), $atts));

    if (empty($name)) $name = zem_contact_label2name($label);

    $email = $zem_contact_submit ? trim(ps($name)) : $default;

    if ($zem_contact_submit and strlen($email))
    {
        if (!is_valid_email($email))
        {
            $zem_contact_error[] = gTxt('zem_contact_reborn_invalid_email', array('{email}' => $email));
            $isError = "errorElement";
        }

        else
        {
            preg_match("/@(.+)$/", $email, $match);
            $domain = $match[1];

            if (is_callable('checkdnsrr') and checkdnsrr('textpattern.com.','A') and !checkdnsrr($domain.'.','MX') and !checkdnsrr($domain.'.','A'))
            {
                $zem_contact_error[] = gTxt('zem_contact_reborn_invalid_host', htmlspecialchars($domain));
                $isError = "errorElement";
            }

            else
            {
                if ($send_article) {
                    $zem_contact_recipient = $email;
                }
                else {
                    $zem_contact_from = $email;
                }
            }
        }
    }

    return zem_contact_text(array(
        'default'    => $email,
        'isError'    => $isError,
        'label'        => $label,
        'max'        => $max,
        'min'        => $min,
        'name'        => $name,
        'required'    => $required,
        'break'        => $break,
        'size'        => $size
    ));
}

function zem_contact_select($atts)
{
    global $zem_contact_error, $zem_contact_submit;

    extract(lAtts(array(
        'name'        => '',
        'break'        => ' ',
        'delimiter'    => ',',
        'isError'    => '',
        'label'        => gTxt('zem_contact_reborn_option'),
        'list'        => gTxt('zem_contact_reborn_general_inquiry'),
        'required'    => 1,
        'selected'    => ''
    ), $atts));

    if (empty($name)) $name = zem_contact_label2name($label);

    $list = array_map('trim', split($delimiter, preg_replace('/[\r\n\t\s]+/', ' ',$list)));

    if ($zem_contact_submit)
    {
        $value = trim(ps($name));

        if (strlen($value))
        {
            if (in_array($value, $list))
            {
                zem_contact_store($name, $label, $value);
            }

            else
            {
                $zem_contact_error[] = gTxt('zem_contact_reborn_invalid_value', htmlspecialchars($label), htmlspecialchars($value));
                $isError = "errorElement";
            }
        }

        elseif ($required)
        {
            $zem_contact_error[] = gTxt('zem_contact_reborn_field_missing', htmlspecialchars($label));
            $isError = "errorElement";
        }
    }
    else
    {
        $value = $selected;
    }

    $out = '';

    foreach ($list as $item)
    {
        $out .= n.t.'<option'.($item == $value ? ' selected="selected">' : '>').(strlen($item) ? htmlspecialchars($item) : ' ').'</option>';
    }

    $zemRequired = $required ? 'zemRequired' : '';

    return '<label for="'.$name.'" class="zemSelect '.$zemRequired.$isError.' '.$name.'">'.htmlspecialchars($label).'</label>'.$break.
        n.'<select id="'.$name.'" name="'.$name.'" class="zemSelect '.$zemRequired.$isError.'">'.
            $out.
        n.'</select>';
}

function zem_contact_checkbox($atts)
{
    global $zem_contact_error, $zem_contact_submit;

    extract(lAtts(array(
        'break'        => ' ',
        'checked'    => 0,
        'isError'    => '',
        'label'        => gTxt('zem_contact_reborn_checkbox'),
        'name'        => '',
        'required'    => 1
    ), $atts));

    if (empty($name)) $name = zem_contact_label2name($label);

    if ($zem_contact_submit)
    {
        $value = (bool) ps($name);

        if ($required and !$value)
        {
            $zem_contact_error[] = gTxt('zem_contact_reborn_field_missing', htmlspecialchars($label));
            $isError = "errorElement";
        }

        else
        {
            zem_contact_store($name, $label, $value ? gTxt('yes') : gTxt('no'));
        }
    }

    else {
        $value = $checked;
    }

    $zemRequired = $required ? 'zemRequired' : '';

    return '<input type="checkbox" id="'.$name.'" class="zemCheckbox '.$zemRequired.$isError.'" name="'.$name.'"'.
        ($value ? ' checked="checked"' : '').' />'.$break.
        '<label for="'.$name.'" class="zemCheckbox '.$zemRequired.$isError.' '.$name.'">'.htmlspecialchars($label).'</label>';
}

function zem_contact_serverinfo($atts)
{
    global $zem_contact_submit;

    extract(lAtts(array(
        'label'        => '',
        'name'        => ''
    ), $atts));

    if (empty($name)) $name = zem_contact_label2name($label);

    if (strlen($name) and $zem_contact_submit)
    {
        if (!$label) $label = $name;
        zem_contact_store($name, $label, serverSet($name));
    }
}

function zem_contact_secret($atts, $thing = '')
{
    global $zem_contact_submit;

    extract(lAtts(array(
        'name'    => '',
        'label'    => gTxt('zem_contact_reborn_secret'),
        'value'    => ''
    ), $atts));

    $name = zem_contact_label2name($name ? $name : $label);

    if ($zem_contact_submit)
    {
        if ($thing) $value = trim(parse($thing));
        zem_contact_store($name, $label, $value);
    }

    return '';
}

function zem_contact_radio($atts)
{
    global $zem_contact_error, $zem_contact_submit, $zem_contact_values;

    extract(lAtts(array(
        'break'        => ' ',
        'checked'    => 0,
        'group'        => '',
        'label'        => gTxt('zem_contact_reborn_option'),
        'name'        => ''
    ), $atts));

    static $cur_name = '';
    static $cur_group = '';

    if (!$name and !$group and !$cur_name and !$cur_group) {
        $cur_group = gTxt('zem_contact_reborn_radio');
        $cur_name = $cur_group;
    }
    if ($group and !$name and $group != $cur_group) $name = $group;

    if ($name) $cur_name = $name;
    else $name = $cur_name;

    if ($group) $cur_group = $group;
    else $group = $cur_group;

    $id   = 'q'.md5($name.'=>'.$label);
    $name = zem_contact_label2name($name);

    if ($zem_contact_submit)
    {
        $is_checked = (ps($name) == $id);

        if ($is_checked or $checked and !isset($zem_contact_values[$name]))
        {
            zem_contact_store($name, $group, $label);
        }
    }

    else
    {
        $is_checked = $checked;
    }

    return '<input value="'.$id.'" type="radio" id="'.$id.'" class="zemRadio '.$name.'" name="'.$name.'"'.
        ( $is_checked ? ' checked="checked" />' : ' />').$break.
        '<label for="'.$id.'" class="zemRadio '.$name.'">'.htmlspecialchars($label).'</label>';
}

function zem_contact_send_article($atts)
{
    if (!isset($_REQUEST['zem_contact_send_article'])) {
        $linktext = (empty($atts['linktext'])) ? gTxt('zem_contact_reborn_send_article') : $atts['linktext'];
        $join = (empty($_SERVER['QUERY_STRING'])) ? '?' : '&';
        $href = $_SERVER['REQUEST_URI'].$join.'zem_contact_send_article=yes';
        return '<a href="'.htmlspecialchars($href).'">'.htmlspecialchars($linktext).'</a>';
    }
    return;
}

function zem_contact_submit($atts, $thing)
{
    extract(lAtts(array(
        'button'    => 0,
        'label'        => gTxt('zem_contact_reborn_send')
    ), $atts));

    $label = htmlspecialchars($label);

    if ($button or strlen($thing))
    {
        return '<button type="submit" class="zemSubmit" name="zem_contact_submit" value="'.$label.'">'.($thing ? trim(parse($thing)) : $label).'</button>';
    }
    else
    {
        return '<input type="submit" class="zemSubmit" name="zem_contact_submit" value="'.$label.'" />';
    }
}

class zemcontact_evaluation
{
    var $status;

    function zemcontact_evaluation() {
        $this->status = 0;
    }

    function add_zemcontact_status($check) {
        $this->status += $check;
    }

    function get_zemcontact_status() {
        return $this->status;
    }
}

function &get_zemcontact_evaluator()
{
    static $instance;

    if(!isset($instance)) {
        $instance = new zemcontact_evaluation();
    }
    return $instance;
}

function zem_contact_label2name($label)
{
    $label = trim($label);
    if (strlen($label) == 0) return 'invalid';
    if (strlen($label) <= 32 and preg_match('/^[a-zA-Z][A-Za-z0-9:_-]*$/', $label)) return $label;
    else return 'q'.md5($label);
}

function zem_contact_store($name, $label, $value)
{
    global $zem_contact_form, $zem_contact_labels, $zem_contact_values;
    $zem_contact_form[$label] = $value;
    $zem_contact_labels[$name] = $label;
    $zem_contact_values[$name] = $value;
}

function zem_contact_mailheader($string, $type)
{
    global $prefs;
    if (!strstr($string,'=?') and !preg_match('/[\x00-\x1F\x7F-\xFF]/', $string)) {
        if ("phrase" == $type) {
            if (preg_match('/[][()<>@,;:".\x5C]/', $string)) {
                $string = '"'. strtr($string, array("\\" => "\\\\", '"' => '\"')) . '"';
            }
        }
        elseif ("text" != $type) {
            trigger_error('Unknown encode_mailheader type', E_USER_WARNING);
        }
        return $string;
    }
    if ($prefs['override_emailcharset']) {
        $start = '=?ISO-8859-1?B?';
        $pcre  = '/.{1,42}/s';
    }
    else {
        $start = '=?UTF-8?B?';
        $pcre  = '/.{1,45}(?=[\x00-\x7F\xC0-\xFF]|$)/s';
    }
    $end = '?=';
    $sep = is_windows() ? "\r\n" : "\n";
    preg_match_all($pcre, $string, $matches);
    return $start . join($end.$sep.' '.$start, array_map('base64_encode',$matches[0])) . $end;
}