<?php
App::uses('Folder', 'Utility');

class FieldNotExistsNewUploadException extends CakeException {}

/**
 * NewUpload Behavior
 *
 * PHP 5.3
 *
 * @package			NewUpload
 * @subpackage		NewUpload.Models.Behavior
 * @version			1.0
 * @license			MIT License (http://www.opensource.org/licenses/mit-license.php)
 *
 * @author			Vitor Pacheco <vitor-p.c@hotmail.com>
 */
class NewUploadBehavior extends ModelBehavior {
/**
 * The default options for the behavior.
 *
 * @access protected
 * @var array
 */
	protected $_defaultOptions = array(
		'dir' => 'files{DS}{ModelName}{DS}',
		'adjustFilename' => 'fix',
		'fields' => array(
			'dir' => 'dir',
			'mimetype' => 'mimetype',
			'size' => 'size',
		),
	);

/**
 * The array that saves the options for the behavior.
 *
 * @access public
 * @var array
 */
	public $config = array();

/**
 * Setup this behavior with the specified configuration settings.
 *
 * @param AppModel $model
 * @param array $config
 * @access public
 * @return void
 * @see ModelBehavior::setup()
 */
	public function setup(AppModel $model, array $config) {
		$this->config[$model->alias] = array();
		foreach ($config as $field => $options) {
			if (!is_array($options)) {
				$field = $options;
				$options = array();
			}
			$options = Set::merge($this->_defaultOptions, $options);

			if ($model->useTable && !$model->hasField($field)) {
				throw new FieldNotExistsNewUploadException(__('The field "%s" doesn\'t exists in the model "%s".', $field, $model->alias));
			}

			$options['dir'] = rtrim($this->_replaceTokens($model, $options['dir'], $field), DS);
			$options['dir'] = $this->_normalizePath($options['dir']);
			foreach ($options['fields'] as $fieldToken => $fieldName) {
				$options['fields'][$fieldToken] = $this->_replaceTokens($model, $fieldName, $field);
			}
			$this->config[$model->alias][$field] = $options;
		}
	}

/**
 * beforeSave is called before a model is saved.
 *
 * @param AppModel $model
 * @return Boolean
 * @see ModelBehavior::beforeSave()
 */
	public function beforeSave(AppModel &$model) {
		$result = $this->_uploadFile($model);
		$allOk = true;
		foreach ($result as $fieldName => $return) {
			if ($return !== true) {
				$model->validationErrors[$fieldName] = $return;
				$allOk = false;
			}
		}
		return $allOk;
	}

/**
 *
 * Enter description here ...
 * @param AppModel $model
 * @access protected
 */
	protected function _uploadFile(AppModel &$model) {
		$data =& $model->data;
		$return = array();
		$folder = new Folder();
		foreach ($this->config[$model->alias] as $fieldName => $options) {
			if (!is_dir($options['dir'])) {
				$folder->create($options['dir']);
			}

			if (empty($data[$model->alias][$fieldName]['name'])) {
				unset($data[$model->alias][$fieldName]);
				$return[$fieldName] = true;
				continue;
			}

			if (!isset($data[$model->alias][$fieldName]) || !is_array($data[$model->alias][$fieldName]) || empty($data[$model->alias][$fieldName]['name'])) {
				if (!empty($data[$model->alias][$model->primaryKey])) {
					unset($data[$model->alias][$fieldName]);
				} else {
					$data[$model->alias][$fieldName] = null;
				}
			}

			$this->_adjustName($model, $fieldName);
			$saveAs = $options['dir'] . DS . $model->data[$model->alias][$fieldName]['name'];

			$copyResults = $this->_copyFileFromTemp($data[$model->alias][$fieldName]['tmp_name'], $saveAs);
			if (true === $copyResults) {
				$return[$fieldName] = $copyResults;
				continue;
			}

			$mimeType = $this->_getMimeType($data[$model->alias][$fieldName]['tmp_name'], $data[$model->alias][$fieldName]['type']);
			if (!empty($options['fields']['dir'])) {
				$data[$model->alias][$options['fields']['dir']] = $options['dir'];
			}
			if (!empty($options['fields']['mimetype'])) {
				$data[$model->alias][$options['fields']['mimetype']] = $mimeType;
			}
			if (!empty($options['fields']['size'])) {
				$data[$model->alias][$options['fields']['size']] = $data[$model->alias][$fieldName]['size'];
			}
			$data[$model->alias][$fieldName] = $data[$model->alias][$fieldName]['name'];

			$return[$fieldName] = true;
			continue;
		}
		return $return;
	}

/**
 *
 * Enter description here ...
 * @param string $file
 * @param string $mimeType
 */
	protected function _getMimeType($file, $mimeType = 'application/octet-stream') {
		if (!is_readable($file)) {
			return $mimeType;
		}
		if ($mimeType !== 'application/octet-stream') {
			return $mimeType;
		}
		if (function_exists('finfo_file')) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mime = finfo_file($finfo, $file);
			if (!empty($mime)) {
				return $mime;
			}
		}
		if (function_exists('mime_content_type')) {
			return mime_content_type($file);
		}
		if (function_exists('getimagesize')) {
			$info = @getimagesize($file);
			if (!empty($info['mime'])) {
				return $info['mime'];
			}
		}
		return $mimeType;
	}

/**
 *
 * Enter description here ...
 * @param string $tmpName
 * @param string $saveAs
 */
	protected function _copyFileFromTemp($tmpName, $saveAs) {
		if (!is_uploaded_file($tmpName)) {
			return false;
		}
		if (!move_uploaded_file($tmpName, $saveAs)) {
			return __('Problems in the copy of the file.', true);
		}
	}

/**
 *
 * Enter description here ...
 * @param AppModel $model
 * @param string $string
 * @param string $field
 */
	protected function _replaceTokens(AppModel &$model, $string, $field) {
		return str_replace(
				array('{ModelName}', '{DS}'),
				array(Inflector::underscore($model->name), DS),
			$string
		);
	}

/**
 *
 * Enter description here ...
 * @param string $path
 */
	protected function _normalizePath($path) {
		if ($path[0] !== '/' && $path[0] !== '\\' && !preg_match('/^[a-z]:/i', $path)) {
			$path = WWW_ROOT . DS . $path;
		}
		return $path;
	}

/**
 *
 * Enter description here ...
 * @param AppModel $model
 * @param string $fieldName
 * @param boolean $checkFile
 */
	protected function _adjustName(AppModel &$model, $fieldName, $checkFile = true) {
		switch ($this->config[$model->alias][$fieldName]['adjustFilename']) {
			case 'fix':
				list($filename, $ext) = $this->_splitFilenameAndExt($model->data[$model->alias][$fieldName]['name']);
				$filename = Inflector::slug($filename);
				$i = 0;
				$newFilename = $filename;
				if (true === $checkFile) {
					while (file_exists($this->config[$model->alias][$fieldName]['dir'] . DS . $newFilename . '.' . $ext)) {
						$newFilename = $filename . '-' . $i++;
					}
				}
				$model->data[$model->alias][$fieldName]['name'] = $newFilename . '.' . $ext;
				break;
			case 'random':
				list(, $ext) = $this->_splitFilenameAndExt($model->data[$model->alias][$fieldName]['name']);
				$model->data[$model->alias][$fieldName]['name'] = uniqid('upload_', true) . '.' . $ext;
				break;
		}
	}

/**
 *
 * Enter description here ...
 * @param string $file
 */
	protected function _splitFilenameAndExt($file) {
		extract(pathinfo($file));
		if (!isset($file)) {
			$file = substr($basename, 0, -1 - count($extension));
		}
		return array($file, $extension);
	}
}