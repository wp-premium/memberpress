const { registerBlockType } = wp.blocks;
const { __ } = wp.i18n;
const { CheckboxControl } = wp.components;

import MPPlaceholder from "../_global/components/mp-placeholder";

registerBlockType("memberpress/login-form", {
  title: __("Login Form", "memberpress"),
  icon: "admin-network", // https://developer.wordpress.org/resource/dashicons/
  category: "memberpress",
  description: __("Display the MemberPress Login form", "memberpress"),
  keywords: [__("membership login form", "memberpress")],
  attributes: {
    use_redirect: { type: "boolean" }
  },
  supports: {
    customClassName: false, // Removes "Custom CSS Class" from "Advanced" tab of block
    html: false // User cannot edit block as HTML
  },
  edit: function({ attributes, setAttributes, className }) {
    const { use_redirect } = attributes;
    return [
      <div className={className}>
        <MPPlaceholder
          icon="admin-network"
          label={__("MemberPress Login Form", "memberpress")}
          instructions={__("Display the MemberPress Login form", "memberpress")}
        >
          <CheckboxControl
            label={
              <span>
                {__("Use MemberPress ", "memberpress")}
                <a
                  href={memberpressBlocks.redirect_url_setting_url}
                  target="_blank"
                >
                  {__("Login Redirect URL?", "memberpress")}
                </a>
              </span>
            }
            checked={use_redirect}
            onChange={use_redirect => {
              setAttributes({ use_redirect });
            }}
          />
        </MPPlaceholder>
      </div>
    ];
  },
  save: function() {
    return null; // Null because we're rendering the output serverside
  }
});
