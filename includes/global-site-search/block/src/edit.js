import { useBlockProps } from '@wordpress/block-editor';

const Edit = () => {
  return (
    <div {...useBlockProps()}>
      <p>Dies ist der Multisite Suche Block</p>
    </div>
  );
};

export default Edit;