<?php
class RasterImage
{
	private $rasterImageResource;
	private $width;
	private $height;

	public function __construct($imagePath)
	{
		$this->rasterImageResource = imagecreatefrompng($imagePath);
		$size = getimagesize($imagePath);
		$this->width = $size[0];
		$this->height = $size[1];
	}

	public function getWidth()
	{
		return $this->width;
	}

	public function getHeight()
	{
		return $this->height;
	}

	public function getColorAt($x, $y)
	{
		$imageColor = imagecolorat($this->rasterImageResource, $x, $y);
		return imagecolorsforindex($this->rasterImageResource, $imageColor);
	}
}
