<?php
class View_Documents extends View
{
	var $_rootpath = NULL;
	var $_realdir = NULL;
	var $_editfile = NULL;
	var $_messages = Array();
	
	function getTitle()
	{
		return NULL;
	}

	function _addMessage($msg) {
		$this->_messages[] = $msg;
	}

	function _dumpMessages() {
		static $i = 0;
		foreach ($this->_messages as $msg) {
			?>
			<div id="msg-<?php echo $i; ?>" class="alert alert-success document-message" ><?php echo ents($msg); ?></div>
			<?php
			$i++;
		}
	}

	function processView()
	{
		$this->_rootpath = Documents_Manager::getRootPath();
		if (!is_dir($this->_rootpath)) {
			trigger_error("Documents root path ".$this->_rootpath.' does not exist, please check your config file', E_USER_ERROR); // exits
		}
		$this->_realdir = $this->_rootpath;
		$this->_messages = Array();
		if (!empty($_REQUEST['dir'])) {
			$this->_realdir = Documents_Manager::validateDirPath($_REQUEST['dir']);
		}

		if ($GLOBALS['user_system']->havePerm(PERM_EDITDOC)) {
			if (!empty($_POST['deletefolder'])) {
				if (rmdir($this->_realdir)) {
					$this->_addMessage('Folder "'.basename($this->_realdir).'" deleted');
					$this->_realdir = dirname($this->_realdir);
				}
			}
			if (!empty($_POST['renamefolder'])) {
				if ($newname = Documents_Manager::validateDirName($_POST['renamefolder'])) {
					$newdir = dirname($this->_realdir).'/'.$newname;
					if (rename($this->_realdir, $newdir)) {
						$this->_addMessage('Folder "'.basename($this->_realdir).'" renamed to "'.$newname.'"');
						$this->_realdir = $newdir;
					}
				}
			}
			if (!empty($_POST['newfolder'])) {
				if ($newname = Documents_Manager::validateDirName($_POST['newfolder'])) {
					$newdir = $this->_realdir.'/'.$newname;
					if (is_dir($newdir) || mkdir($newdir)) {
						if ($p = fileperms($this->_rootpath)) chmod($newdir, $p);
						$this->_addMessage('Folder "'.$newname.'" created');
						$this->_realdir = $newdir;
					}
				}
			}
			if (!empty($_FILES['newfile'])) {
				foreach ($_FILES['newfile']['error'] as $key => $error) {
					if ($error == UPLOAD_ERR_OK) {
						$tmp_name = $_FILES["newfile"]["tmp_name"][$key];
						if ($name = Documents_Manager::validateFileName($_FILES["newfile"]["name"][$key])) {
							if (move_uploaded_file($tmp_name, $this->_realdir.'/'.$name)) {
								if ($p = fileperms($this->_rootpath)) chmod($this->_realdir.'/'.$name, $p);
								$this->_addMessage('File "'.$name.'" saved');
							}
						}
					} else if (in_array($error, Array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE))) {
						add_message("Your file could not be saved because the file is too big.", 'error');
						return NULL;
					} else {
						trigger_error("Technical error uploading photo file: Error #".$error, E_USER_ERROR);
					}
				}
			}
			if (!empty($_FILES['replacefile'])) {
				foreach ($_FILES['replacefile']['error'] as $origname => $error) {
					if (($error == UPLOAD_ERR_OK) && ($origname = Documents_Manager::validateFileName($origname))) {
						$tmp_name = $_FILES["replacefile"]["tmp_name"][$origname];
						$origname = urldecode($origname);
						if (file_exists($this->_realdir.'/'.$origname)) {
							if (move_uploaded_file($tmp_name, $this->_realdir.'/'.$origname)) {
								if ($p = fileperms($this->_rootpath)) chmod($this->_realdir.'/'.$origname, $p);
								$this->_addMessage('File "'.$origname.'" replaced');
							}
						}
					}
				}
			}
			if (!empty($_POST['deletefile'])) {
				foreach ($_POST['deletefile'] as $delname) {
					if ($delname = Documents_Manager::validateFileName($delname)) {
						if (file_exists($this->_realdir.'/'.$delname) && unlink($this->_realdir.'/'.$delname)) {
							$this->_addMessage('File "'.$delname.'" deleted');
						}
					}
				}
			}
			if (!empty($_POST['renamefile'])) {
				foreach ($_POST['renamefile'] as $origname => $newname) {
					$origname = urldecode($origname);
					if (($newname = Documents_Manager::validateFileName($newname)) && ($origname = Documents_Manager::validateFileName($origname))) {
						if (file_exists($this->_realdir.'/'.$origname) && rename($this->_realdir.'/'.$origname, $this->_realdir.'/'.$newname)) {
							$this->_addMessage("$origname renamed to $newname");
						}
					}
				}
			}
			if (!empty($_POST['movefile'])) {
				foreach ($_POST['movefile'] as $filename => $newdir) {
					$filename = urldecode($filename);
					if (($filename = Documents_Manager::validateFileName($filename)) && ($fulldir = Documents_Manager::validateDirPath($newdir))) {
						if (rename($this->_realdir.'/'.$filename, $fulldir.'/'.$filename)) {
							$this->_addMessage("\"$filename\" moved to folder \"$newdir\"");
						}
					}
				}
			}
			if (!empty($_REQUEST['editfile'])) {
				if ($_REQUEST['editfile'] == '_new_') {
					$this->_editfile = '_new_';
				} else {
					$this->_editfile = Documents_Manager::validateFileName($_REQUEST['editfile']);
				}
			}
			if (!empty($_POST['savefile'])) {
				if ($filename = Documents_Manager::validateFileName($_POST['savefile'])) {
					if (!Documents_Manager::isHTML($filename)) {
						// Append .html if entered filename has missing or non-HTML extension
						$filename.+".html";
					}
					if (!empty($_POST['isnew']) && file_exists($this->_realdir.'/'.$filename)) {
						trigger_error("$filename already exists in this folder.  Please choose another name.");
						$this->_editfile = $filename;
					} else {
						if (file_put_contents($this->_realdir.'/'.$filename, process_widget('contents', array('type' => 'html')))) {
							if ($p = fileperms($this->_rootpath)) chmod($this->_realdir.'/'.$filename, $p);
							$this->_addMessage("\"$filename\" saved");
						}
					}
				}
			}
		}
	}

	// Given a fullly qualified path, returns the portion that we should show to the user
	// eg /var/www/jethro/files/foo/bar becomes /foo/bar
	function getPrintedDir($dir=NULL)
	{
		if (is_null($dir)) $dir = $this->_realdir;
		return str_replace($this->_rootpath, '', $dir);
	}

	function printView()
	{
		$id = ($this->getPrintedDir() == '') ? ' id="current-folder"' : '';
		?>
		<div class="documents-container clearfix">
			<div class="documents-tree well">
				<a href="<?php echo build_url(Array('dir'=>NULL)); ?>"<?php echo $id; ?>>Top Level</a>
				<?php $this->_printFolderTree(); ?>
			</div>
			<div class="documents-body">
				<?php $this->_dumpMessages(); ?>
				<?php 
				if (!empty($this->_editfile)) {
					$this->printEditor();
				} else {
					$this->printFolderContents();
				}
				?>
			</div>
		</div>
			<div id="rename-folder-modal" class="modal hide fade" role="dialog" aria-hidden="true">
				<form method="post">
					<div class="modal-header">
						<h4>Rename this folder:</h4>
					</div>
					<div class="modal-body">
						Folder name: <input type="text" name="renamefolder" value="<?php echo ents(basename($this->getPrintedDir())); ?>" />
					</div>
					<div class="modal-footer">
						<button type="submit" class="btn" accesskey="s">Go</button>
						<button class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
					</div>
				</form>
			</div>

			<div id="add-folder-modal" class="modal hide fade" role="dialog" aria-hidden="true">
				<form method="post">
					<div class="modal-header">
						<h4>Create new subfolder:</h4>
					</div>
					<div class="modal-body">
						Folder name: <input type="text" name="newfolder" />
					</div>
					<div class="modal-footer">
						<button type="submit" class="btn" accesskey="s">Go</button>
						<button class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
					</div>
				</form>
			</div>

			<div id="upload-file-modal" class="modal hide fade" role="dialog" aria-hidden="true">
				<form method="post" enctype="multipart/form-data">
					<div class="modal-header">
						<h4>Upload new files:</h4>
					</div>
					<div class="modal-body">
						<input type="file" name="newfile[]" multiple="multiple" max-bytes="<?php echo file_upload_max_size(); ?>" />
						<p class="upload-progress hide">Uploading...<br /><img src="resources/img/progress.gif" /></p>
					</div>
					<div class="modal-footer">
						<button type="submit" class="btn" accesskey="s">Go</button>
						<button class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
					</div>
				</form>
			</div>

			<div id="replace-file-modal" class="modal hide fade" role="dialog" aria-hidden="true">
				<form method="post" enctype="multipart/form-data">
					<div class="modal-header">
						<h4>Replace <span id="replaced-filename"></span> with:</h4>
					</div>
					<div class="modal-body">
						<input type="file" id="replace-file" name="replacefile[X]" max-bytes="<?php echo file_upload_max_size(); ?>" />
						<p class="upload-progress hide">Uploading...<br /><img src="resources/img/progress.gif" /></p>
					</div>
					<div class="modal-footer">
						<button type="submit" class="btn" accesskey="s">Go</button>
						<button class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
					</div>
				</form>
			</div>

			<div id="rename-file-modal" class="modal hide fade" role="dialog" aria-hidden="true">
				<form method="post">
					<div class="modal-header">
						<h4>Rename file:</h4>
					</div>
					<div class="modal-body">
						<input type="text" class="select-basename" id="rename-file" name="renamefile[X]" value="" />
					</div>
					<div class="modal-footer">
						<button type="submit" class="btn" accesskey="s">Go</button>
						<button class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
					</div>
				</form>
			</div>

			<div id="move-file-modal" class="modal hide fade" role="dialog" aria-hidden="true">
				<form method="post">
					<div class="modal-header">
						<h4>Move <span id="moving-filename"></span> <br />to a different folder:</h4>
					</div>
					<div class="modal-body">
						<select id="move-file" name="movefile[X]" style="width: 70%">
							<option value="/">[Top level]</option>
							<?php $this->_printFolderOptions(); ?>
						</select>
					</div>
					<div class="modal-footer">
						<button type="submit" class="btn" accesskey="s">Go</button>
						<button class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
					</div>
				</form>
			</div>
		<?php
	}

	function _printFolderOptions($dir=NULL, $indent='')
	{
		if (is_null($dir)) $dir = $this->_rootpath;
		$di = new DirectoryIterator($dir);
		if (!$di->valid()) return; // nothing to list
		$currentprinted = $this->getPrintedDir();
		foreach ($di as $fileinfo) {
			if ($fileinfo->isDir() && !$fileinfo->isDot()) {
				$printed_dir = $this->getPrintedDir($fileinfo->getPath().'/'.$fileinfo->getFilename());
				$sel = ($printed_dir == $currentprinted) ? ' selected="seelected"' : '';
				?>
				<option value="<?php echo ents($printed_dir); ?>"<?php echo $sel; ?>><?php echo nbsp(ents($indent.$fileinfo->getFilename())); ?></option>
				<?php
				if (strlen($indent) < 3) {
					// going too far down into the tree is too slow, limit ourselves to depth 4
					$this->_printFolderOptions($dir.'/'.$fileinfo->getFilename(), $indent.'   ');
				}
			}
		}
	}

	function _printFolderTree($dir=NULL)
	{
		if (is_null($dir)) $dir = $this->_rootpath;
		$di = new DirectoryIterator($dir);
		if (!$di->valid()) return; // nothing to list

		?>
		<ul>
		<?php
		$currentprinted = $this->getPrintedDir();
		$dirlist = Array();
		foreach ($di as $fileinfo) {
			if ($fileinfo->isDir() && !$fileinfo->isDot() && substr($fileinfo->getFilename(), 0, 1) != '.') {
				$dirlist[] = $fileinfo->getPath().'/'.$fileinfo->getFilename();
			}
		}
		natsort($dirlist);
		foreach ($dirlist as $dirpath) {
				$printed_dir = $this->getPrintedDir($dirpath);
				$id = ($printed_dir == $currentprinted) ? ' id="current-folder"' : '';
				?>
				<li>
					<div <?php echo $id; ?>>
					<a href="<?php echo build_url(Array('dir'=>$printed_dir, 'editfile'=>NULL)); ?>"><?php echo ents(basename($dirpath)); ?></a>
					</div>
					<?php
					if (0 === strpos($currentprinted, $printed_dir)) {
						$this->_printFolderTree($dirpath);
					}
					?>
				</li>
				<?php
		}
		?>
		</ul>
		<?php
	}

	function printEditor()
	{
		?>
		<form method="post" action="<?php echo build_url(Array('editfile' => NULL)); ?>">
		<?php
		if ($this->_editfile == '_new_') {
			$i = 1;
			while (file_exists($this->_realdir.'/newfile'.$i.'.html')) $i++;
			?>
			<p><b>Filename: </b><input name="savefile" class="select-basename" type="text" value="newfile<?php echo $i; ?>.html" /></p>
			<input type="hidden" name="isnew" value="1" />
			<?php
			$content = '';
		} else {
			?>
			<input type="hidden" name="savefile" value="<?php echo $this->_editfile; ?>" />
			<h3><?php echo ents($this->_editfile); ?></h3>
			<?php
			$content = file_get_contents($this->_realdir.'/'.$this->_editfile);
		}

		print_widget('contents', Array('type' => 'html'), $content);
		?>
		<p class="align-right">
			<input type="submit" class="btn" value="Save" />
			<a class="btn" href="<?php echo build_url(Array('editfile' => NULL)); ?>">Cancel</a>
		</p>
		</form>
		<?php
	}

	function printFolderContents()
	{
		$di = new DirectoryIterator($this->_realdir);
		$dirlist = $dirinfo = Array();
		$filelist = $fileinfo = Array();
		foreach ($di as $file) {
			if ($file->isDir() && !$file->isDot() && substr($file->getFilename(), 0, 1) != '.') {
				$dirlist[] = $file->getFilename();
				$dirinfo[$file->getFilename()] = array('size' => $file->getSize(), 'mtime' => $file->getMTime());
			}
			if ($file->isFile() && !$file->isDot() && substr($file->getFilename(), 0, 1) != '.') {
				$filelist[] = $file->getFilename();
				$fileinfo[$file->getFilename()] = array('size' => $file->getSize(), 'mtime' => $file->getMTime());
			}
		}
		natsort($dirlist);
		natsort($filelist);

		if ($GLOBALS['user_system']->havePerm(PERM_EDITDOC)) {
			$parentaction = build_url(Array('call'=>NULL,'view'=>'documents','dir'=>$this->getPrintedDir()));
			?>
			<h2>
				<?php 
				$title = $this->getPrintedDir();
				if (empty($title)) $title = '(Top Level)';
				echo 'Documents: '.$title;
				?>
			</h2>
			<div class="pull-right document-icons">
			<?php
			if ($this->getPrintedDir()) {
				if (empty($dirlist) && empty($filelist)) {
					?>
					<form class="min" method="post" target="_parent" action="<?php echo $parentaction ?>">
						<input type="hidden" name="deletefolder" value="1" />
						<input type="image" title="Delete this folder" class="confirm-title" src="resources/img/folder_delete.png" />
					</form>
					<?php
				}
				?>
				<a href="#rename-folder-modal" data-toggle="modal"><img title="Rename this folder" src="resources/img/folder_edit.png"/></a>
				<?php
			}
			?>
				<a href="#add-folder-modal" data-toggle="modal"><img title="Add new sub-folder" data-togl src="resources/img/folder_add.png" /></a>

				<a href="<?php echo build_url(Array('editfile' => '_new_')); ?>"><img title="Edit new HTML document" src="resources/img/document_new.png" /></a>

				<a href="#upload-file-modal" data-toggle="modal"><img title="Upload new file" src="resources/img/document_upload.png" /></a>
			</div><!-- .document-icons -->

			<?php
		}
		?>
		<br style="clear: both" />
		<?php
		if (empty($filelist) && empty($dirlist)) {
			?>
			<p><i>There are no files in this folder</i></p>
			<p class="parent-folder"><a href="<?php echo build_url(Array('dir' => $this->getPrintedDir(dirname($this->_realdir)))); ?>"><i class="icon-circle-arrow-up"></i>Parent folder</a></p>
			<?php
			return;
		} else {
			?>
			<table class="table table-condensed table-hover table-striped">
				<thead>
					<tr>
						<th>Filename</th>
						<th class="file-detail">Size</th>
						<th class="file-detail">Last Modified</th>
					<?php
					if ($GLOBALS['user_system']->havePerm(PERM_EDITDOC)) {
						?>
						<th>Actions</th>
						<?php
					}
					?>
					</tr>
				</thead>
				<tbody>
				<?php
				if (!empty($_REQUEST['dir'])) {
					?>
					<tr class="parent-folder">
						<td class="filename"><a href="<?php echo build_url(Array('dir' => $this->getPrintedDir(dirname($this->_realdir)))); ?>"><i class="icon-circle-arrow-up"></i>Parent folder</a></td>
						<td class="file-detail">&nbsp;</td>
						<td class="file-detail">&nbsp;</td>
						<td class="narrow">&nbsp;</td>
					</tr>
					<?php
				}
				?>
			<?php
			foreach ($dirlist as $dirname) {
				?>
				<tr>
					<td class="filename middle">
						<a href="<?php echo build_url(array('call'=>null, 'view' => 'documents', 'dir' => $this->getPrintedDir().'/'.$dirname)); ?>" target="_parent">
						<img src="resources/img/folder.png" style="margin-right: 5px" /><?php echo ents($dirname); ?></a>
					</td>
					<td class="file-detail">&nbsp;</td>
					<td class="file-detail"><?php echo format_datetime($dirinfo[$dirname]['mtime']); ?></td>
					<td class="narrow">&nbsp;</td>
				</tr>
				<?php
			}
			$i = 0;
			foreach ($filelist as $filename) {
				?>
				<tr>
					<td class="filename"><a href="<?php echo $this->_getFileURL($filename); ?>"><?php echo ents($filename); ?></a></td>
					<td class="file-detail"><?php echo $this->_getFriendlySize($fileinfo[$filename]['size']); ?></td>
					<td class="file-detail"><?php echo format_datetime($fileinfo[$filename]['mtime']); ?></td>
				<?php
				if ($GLOBALS['user_system']->havePerm(PERM_EDITDOC)) {
					?>
					<td class="narrow action-cell">
						<span class="clickable rename-file"><i class="icon-wrench"></i>Rename</span> &nbsp;
						<span class="clickable replace-file"><i class="icon-upload"></i>Replace</span> &nbsp;
						<span class="clickable move-file"><i class="icon-random"></i>Move</span> &nbsp;
						<form method="post" class="min">
							<input type="hidden" name="deletefile[]" value="<?php echo ents($filename);?>" ?>
							<button type="submit" class="btn btn-link confirm-title" title="Delete this file">
								<i class="icon-trash"></i>Delete</button>
						</form>&nbsp;
					<?php
					if (Documents_Manager::isHTML($filename)) {
						?>
						<a href="<?php echo build_url(array('editfile' => $filename)); ?>"><i class="icon-pencil"></i>Edit</a> &nbsp;
						<?php
					}
					?>
					</td>
					<?php
				}
				?>
				</tr>
				<?php
			}
			?>
				</tbody>
			</table>
			<?php
		}
	}

	function _getFriendlySize($size)
	{
		$units = 'B';
		if ($size > 1024) {
			$size = floor($size / 1024);
			$units = 'kB';
		}
		if ($size > 1024) {
			$size = number_format($size / 1024, 1);
			$units = 'MB';
		}
		return $size.$units;
	}

	function _getFileURL($filename)
	{
		return build_url(array('call'=>'documents', 'getfile'=>$filename));
	}

	static function getMenuPermissionLevel()
	{
		return PERM_VIEWDOC;
	}

	function serveFile()
	{
		if (($filename = Documents_Manager::validateFileName($_REQUEST['getfile'])) && file_exists($this->_realdir.'/'.$filename)) {
			Documents_Manager::serveFile($this->_realdir.'/'.$filename);
		}
	}

	function serveZip()
	{
		// array of files to zip
		$downloadFilename = array_get($_REQUEST, 'zipname', 'JethroFiles');
		if (substr($downloadFilename, -4) != '.zip') $downloadFilename .= '.zip';
		$zip = new ZipArchive();
		$zipFilename = tempnam(sys_get_temp_dir(), 'jethrozip');
		rename($zipFilename, $zipFilename.".zip");
		$zipFilename .= ".zip";
		if ($zip->open($zipFilename, ZipArchive::CREATE)!==TRUE) {
			exit("cannot open <$zipFilename>\n");
		}
		foreach ((array)$_REQUEST['zipfile'] as $filename) {
			if (($dir = Documents_Manager::validateDirPath(dirname($filename)))
					&& ($filename = Documents_Manager::validateFileName(basename($filename)))
			) {
				if (!$zip->addFile($dir.'/'.$filename, $filename)) {
					trigger_error("Failed adding $filename");
					exit;
				}
			}
		}
		$zip->close();
		header("Pragma: public"); // required
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private",false); // required for certain browsers
		header("Content-Transfer-Encoding: binary");
		header('Content-Disposition: attachment; filename="'.$downloadFilename.'"');
		readfile($zipFilename);
		unlink($zipFilename);
	}


}