<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
     
    </style>
</head>
<body>

<div id="asterniccontent">
    <div id='asternicheader'>
        <ul id='primary'>
            <?php
            $tab = isset($_GET['tab']) ? $_GET['tab'] : 'home';

            $menu = [_('Home'), _('Outgoing'), _('Incoming'), _('Combined'), _('Distribution')];

            $link = [
                "?type=tool&display=asternic_cdr&tab=home",
                "?type=tool&display=asternic_cdr&tab=outgoing",
                "?type=tool&display=asternic_cdr&tab=incoming",
                "?type=tool&display=asternic_cdr&tab=combined",
                "?type=tool&display=asternic_cdr&tab=distribution"
            ];

            
          
            foreach ($menu as $index => $menuItem) {
                $isActive = preg_match("/tab=$tab/", $link[$index]);
                $class = $isActive ? 'active' : '';
            
                echo "<li class='$class'>";
                if ($isActive) {
                    echo "<span>$menuItem</span>";
                } else {
                    // Add JavaScript to open the link in a new tab and switch to it
                    echo "<a href='javascript:void(0);' onclick=\"window.open('" . $link[$index] . "', '_blank').focus();\">$menuItem</a>";
                }
                echo "</li>";
            }
            
            ?>
        </ul>
    </div>

    <!-- Other content of asterniccontent goes here -->

</div>

</body>
</html>
