import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { TextControl } from '@wordpress/components';
import { useBlockProps } from '@wordpress/block-editor';

registerBlockType('global-site-search/search', {
  title: __('Netzwerksuche', 'global-site-search'),
  icon: 'search',
  category: 'widgets',

  edit: (props) => {
    const { attributes, setAttributes } = props;

    const onChangeTitle = (newTitle) => {
      setAttributes({ title: newTitle });
    };

    return (
      <div {...useBlockProps()}>
        <TextControl
          label={__('Titel', 'global-site-search')}
          value={attributes.title}
          onChange={onChangeTitle}
        />
      </div>
    );
  },

  save: () => {
    // Da der Block serverseitig gerendert wird, speichern wir hier nichts
    return null;
  }
});