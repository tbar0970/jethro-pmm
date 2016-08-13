<?php

function startLayout($withDrawer=true) {
?>
<div class="bmd-layout-container<?php if ($withDrawer) echo ' bmd-drawer-f-l bmd-drawer-overlay-md-down bmd-drawer-in-lg-up';?>">
  <header class="bmd-layout-header">
    <div class="navbar navbar-light bg-faded">
      <?php if ($withDrawer) {
      echo '<button class="navbar-toggler hidden-lg-up" type="button" data-toggle="drawer" data-target="#my-drawer">
        <span class="sr-only">Toggle drawer</span>
        <i class="material-icons">menu</i>
      </button>';
      }
      ?>
      <ul class="nav navbar-nav">
        <li class="nav-item">
          <?php echo SYSTEM_NAME; ?>
        </li>
      </ul>
      <ul class="nav navbar-nav pull-xs-right">
        <li class="nav-item hidden-sm-down">
          <button class="btn bmd-btn-icon" title="Drawer force close" id="drawer-visibility">
            <i class="material-icons">visibility</i>
          </button>
        </li>
        <li class="nav-item hidden-sm-down">
          <button class="btn bmd-btn-icon" title="Drawer left" id="drawer-f-l">
            <i class="material-icons">border_left</i>
          </button>
        </li>
        <li class="nav-item hidden-sm-down">
          <button class="btn bmd-btn-icon" title="Drawer right" id="drawer-f-r">
            <i class="material-icons">border_right</i>
          </button>
        </li>
        <li class="nav-item hidden-sm-down">
          <button class="btn bmd-btn-icon" title="Drawer top" id="drawer-f-t">
            <i class="material-icons">border_top</i>
          </button>
        </li>
        <li class="nav-item hidden-sm-down">
          <button class="btn bmd-btn-icon" title="Drawer bottom" id="drawer-f-b">
            <i class="material-icons">border_bottom</i>
          </button>
        </li>

        <li class="nav-item">
          <div class="bmd-form-group bmd-collapse-inline pull-xs-right">
            <button class="btn bmd-btn-icon" for="search" data-toggle="collapse" data-target="#collapse-search" aria-controls="collapse-search">
              <i class="material-icons">search</i>
            </button>
            <span id="collapse-search" class="collapse">
              <input class="form-control" type="text" id="search" placeholder="Enter your query...">
            </span>
          </div>
        </li>
        <li class="nav-item">
          <div class="dropdown">
            <button class="btn bmd-btn-icon btn-secondary dropdown-toggle" type="button" id="more-menu" data-toggle="dropdown" aria-haspopup="true" >
              <i class="material-icons">more_vert</i>
            </button>
            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="more-menu">
              <button class="dropdown-item" type="button">About</button>
              <button class="dropdown-item" type="button">Contact</button>
              <button class="dropdown-item" type="button">Legal information</button>
            </div>
          </div>
        </li>
      </ul>
    </div>
  </header>
<?php if ($withDrawer) showDrawer(); ?>
  <main class="bmd-layout-content">
    <div class="container">
<?php
}

function showDrawer() {
?>
  <div id="my-drawer" class="bmd-layout-drawer">
    <header>
      <img src="images/user.jpg" class="avatar">
      <div class="account">
        <span>hello@example.com</span>
        <div class="dropdown">
          <button class="btn bmd-btn-icon dropdown-toggle" type="button" id="nav-account-menu" data-toggle="dropdown" aria-haspopup="true">
            <i class="material-icons">arrow_drop_down</i>
          </button>
          <div class="dropdown-menu dropdown-menu-right" aria-labelledby="nav-account-menu">
            <a class="dropdown-item">hello@example.com</a>
            <a class="dropdown-item">info@example.com</a>
            <a class="dropdown-item"><i class="material-icons">add</i>Add account...</a>
          </div>
        </div>
      </div>
    </header>
    <ul class="list-group">
      <a class="list-group-item">
        <i class="material-icons" role="presentation">home</i>Home
      </a>
      <a class="list-group-item">
        <i class="material-icons" role="presentation">inbox</i>Inbox
      </a>
      <a class="list-group-item">
        <i class="material-icons" role="presentation">delete</i>Trash
      </a>
      <a class="list-group-item">
        <i class="material-icons" role="presentation">report</i>Spam
      </a>
      <a class="list-group-item">
        <i class="material-icons" role="presentation">forum</i>Forums
      </a>
      <a class="list-group-item">
        <i class="material-icons" role="presentation">flag</i>Updates
      </a>
      <a class="list-group-item">
        <i class="material-icons" role="presentation">local_offer</i>Promos
      </a>
      <a class="list-group-item">
        <i class="material-icons" role="presentation">shopping_cart</i>Purchases
      </a>
      <a class="list-group-item">
        <i class="material-icons" role="presentation">people</i>Social
      </a>

      <li class="bmd-layout-spacer"></li>
      <a class="list-group-item">
        <i class="material-icons" role="presentation">help_outline</i><span class="sr-only">Help</span>
      </a>
    </ul>
  </div>
<?php
}
function finishLayout() {
?>
    </div>
  </main>
</div>
<?php
}
