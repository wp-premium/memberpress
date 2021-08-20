const { registerBlockType } = wp.blocks;
const { __ } = wp.i18n;
const { InspectorControls, InnerBlocks } = wp.editor;
const { PanelBody, PanelRow, RadioControl, SelectControl, TextareaControl } = wp.components;
const { rules } = memberpressBlocks;

import MPPlaceholder from "../_global/components/mp-placeholder";
import "./editor.scss";

registerBlockType("memberpress/protected-content", {
  title: __("Protected", "memberpress"),
  description: __(
    "Layout blocks and content protected by MemberPress.",
    "memberpress"
  ),
  icon: "lock",
  category: "memberpress",
  keywords: [__("login protected membership hidden rule", "memberpress")],
  supports: {
    customClassName: false, // Removes "Custom CSS Class" from "Advanced" tab of block
    html: false // User cannot edit block as HTML
  },
  attributes: {
    rule: {
      type: "string"
    },
    ifallowed: {
      type: "string"
    },
    unauth: {
      type: "string"
    },
    unauth_message: {
      type: "string"
    }
  },
  edit({ attributes, setAttributes, className }) {
    const { rule, ifallowed, unauth, unauth_message } = attributes;
    const ruleInfo = rules.filter(r => r.value === parseInt(rule)).pop();
    return [
      <InspectorControls>
        <PanelBody title={__("Access Rule", "memberpress")}>
          <PanelRow>
            <SelectControl
              value={rule ? rule : ""}
              help={__(
                "Select a Rule to determine member access.",
                "memberpress"
              )}
              options={[
                {
                  label: __("Select a Rule", "memberpress"),
                  value: ""
                },
                ...rules
              ]}
              onChange={rule => {
                setAttributes({
                  rule
                });
              }}
            />
          </PanelRow>
          {ruleInfo && ruleInfo.ruleLink != "" && (
            <div>
              <PanelRow>
                <a href={ruleInfo.ruleLink} target="_blank">
                  {__("View Rule", "memberpress")}
                </a>
              </PanelRow>
            </div>
          )}
          <PanelRow>
            <RadioControl
                    label={__("If Allowed", "memberpress")}
                    help={__("When set to \"show\", the content is shown to authorized members only. When set to \"hide\", the content is hidden from authorized members.", "memberpress")}
                    selected={ifallowed ? ifallowed : "show"}
                    options={ [
                        { label: __("Show", "memberpress"), value: 'show' },
                        { label: __("Hide", "memberpress"), value: 'hide' },
                    ] }
                    onChange={ifallowed => {
                      setAttributes({
                        ifallowed
                      });
                    }}
                />
          </PanelRow>
        </PanelBody>
        <PanelBody
          title={__("Unauthorized Access", "memberpress")}
          initialOpen={false}
        >
          <PanelRow>
            <SelectControl
              label={__("Unauthorized Action", "memberpress")}
              value={unauth ? unauth : ""}
              help={__(
                "Specify how to handle unauthorized access.",
                "memberpress"
              )}
              options={[
                {
                  label: __("Hide Only", "memberpress"),
                  value: ""
                },
                {
                  label: __("Show Message", "memberpress"),
                  value: "message"
                },
                {
                  label: __("Show Login Form", "memberpress"),
                  value: "login"
                },
                {
                  label: __("Show Login Form & Message", "memberpress"),
                  value: "both"
                }
              ]}
              onChange={option => {
                setAttributes({
                  unauth: option
                });
              }}
            />
          </PanelRow>
          {unauth && (unauth === "message" || unauth === "both") && (
            <PanelRow>
              <TextareaControl
                label={__("Unauthorized Message", "memberpress")}
                help={__(
                  "Message to show on Unauthorized Access",
                  "memberpress"
                )}
                value={unauth_message ? unauth_message : ""}
                onChange={text => {
                  setAttributes({
                    unauth_message: text
                  });
                }}
              />
            </PanelRow>
          )}
        </PanelBody>
      </InspectorControls>,

      <div className={className}>
        <MPPlaceholder
          icon="lock"
          label={__("MemberPress Protected Content", "memberpress")}
          instructions={__(
            "Add child blocks that will only be shown to authorized members.",
            "memberpress"
          )}
        />
        <InnerBlocks />
      </div>
    ];
  },
  save() {
    return (
      <div>
        <InnerBlocks.Content />
      </div>
    );
  }
});
