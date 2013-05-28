<?php

class zem_contact_reborn
{
    /**
     * Constructor.
     */

    public function __construct()
    {
        register_callback(array($this, 'install'), 'plugin_lifecycle.zem_contact_reborn', 'installed');
        register_callback(array($this, 'uninstall'), 'plugin_lifecycle.zem_contact_reborn', 'deleted');
    }

    /**
     * Installer.
     */

    public function install()
    {
        set_pref('zem_contact_reborn_secret', md5(uniqid(mt_rand(), true)), 'zem_contact_reborn', PREF_HIDDEN, 'text_input', 80);
    }

    /**
     * Uninstaller.
     */

    public function uninstall()
    {
        safe_delete('txp_prefs', "name like 'zem\_contact\_reborn\_%'");
    }
}

new zem_contact_reborn();


function zem_contact($atts, $thing = null)
{
    extract(lAtts(array(
        'form'         => '',
        'from'         => '',
        'label'        => gTxt('zem_contact_reborn_contact'),
        'redirect'     => '',
        'send_article' => 0,
        'subject'      => null,
        'to'           => '',
        'thanks'       => graf(gTxt('zem_contact_reborn_email_thanks')),
        'thanks_form'  => '',
    ), $atts));

    if ($subject === null)
    {
        $subject = gTxt('zem_contact_reborn_email_subject', array(
            '{sitename}' => get_pref('sitename'),
        ), false);
    }

    extract(psa(array(
        'zem_contact_id',
        'zem_contact_stamp',
        'zem_contact_token',
    )));

    if (headers_sent() === false)
    {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s',time()-3600*24*7).' GMT');
        header('Expires: '.gmdate('D, d M Y H:i:s',time()+600).' GMT');
        header('Cache-Control: no-cache, must-revalidate');
    }

    $form_id = md5(json_encode(array_merge($atts, array('thing' => $thing))));

    if ($zem_contact_id && $form_id === $zem_contact_id)
    {
        // Validates the token.

        if ($zem_contact_token !== md5(get_pref('zem_contact_reborn_secret') . $zem_contact_id . $zem_contact_stamp))
        {
            return;
        }

        // Checks if the form is expired.

        if ($zem_contact_stamp > strtotime('-2 hour'))
        {
            return;
        }

        // Checks if the form is used.

        if (safe_row('used', 'txp_discuss_nonce', "nonce = '".doSlash($zem_contact_id)."' and used = 1 and issue_time = '".strftime('%Y-%m-%d %H:%M:%s', strtotime('+100 minute'))."'"))
        {
            $zem_contact_error[] = gTxt('zem_contact_reborn_form_used');
        }

        // Saving.

        callback_event('zem_contact.send', '', 0, array(
            'data' => $data,
        ));
    }
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