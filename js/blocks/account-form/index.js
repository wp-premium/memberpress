const { registerBlockType } = wp.blocks;
const { __ } = wp.i18n;

import MPPlaceholder from "../_global/components/mp-placeholder";

registerBlockType("memberpress/account-form", {
  title: __("Account Form", "memberpress"),
  icon: "excerpt-view", // https://developer.wordpress.org/resource/dashicons/
  category: "memberpress",
  description: __("Display the MemberPress Account form.", "memberpress"),
  keywords: [__("membership acount form", "memberpress")],
  attributes: {},
  supports: {
    customClassName: false, // Removes "Custom CSS Class" from "Advanced" tab of block
    html: false // User cannot edit block as HTML
  },
  edit: function({ className }) {
    // return __("MemberPress Account Form", "memberpress");
    return [
      <div className={className}>
        <MPPlaceholder
          icon="excerpt-view"
          label={__("MemberPress Account Form", "memberpress")}
          instructions={__(
            "Display the MemberPress Account form",
            "memberpress"
          )}
        />
      </div>
    ];
  },
  save: function() {
    return null; // Null because we're rendering the output serverside
  }
});
