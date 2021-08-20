const { registerBlockType } = wp.blocks;
const { __ } = wp.i18n;
const { SelectControl } = wp.components;
const { memberships } = memberpressBlocks;

import MPPlaceholder from "../_global/components/mp-placeholder";

registerBlockType("memberpress/membership-signup", {
  title: __("Registration", "memberpress"),
  icon: "groups", // https://developer.wordpress.org/resource/dashicons/
  category: "memberpress",
  description: __(
    "Display a signup form for a MemberPress membership.",
    "memberpress"
  ),
  keywords: [__("membership signup form", "memberpress")],
  attributes: {
    membership: { type: "string" }
  },
  supports: {
    customClassName: false, // Removes "Custom CSS Class" from "Advanced" tab of block
    html: false // User cannot edit block as HTML
  },
  edit: function({ attributes, setAttributes, className }) {
    const { membership } = attributes;

    return [
      <div className={className}>
        <MPPlaceholder
          icon="groups"
          label={__("MemberPress Signup Form", "memberpress")}
          instructions={__(
            "Display a signup form for a MemberPress membership.",
            "memberpress"
          )}
        >
          <SelectControl
            label={__(
              "Select a Membership registration form to display",
              "memberpress"
            )}
            value={membership}
            options={[
              {
                label: __("-- Select a Membership", "memberpress"),
                value: ""
              },
              ...memberships
            ]}
            onChange={membership => {
              setAttributes({
                membership
              });
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
