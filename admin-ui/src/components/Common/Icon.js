import React from 'react';
import { CommonContext } from "../../Context";

const Icon = ({
                  icon,
                  fontSize = "text-sm 2xl:text-md",
                  fontWeight = "font-medium",
                  color,
                  click,
                  opactity,
                  width = "",
                  show = "",
                  extraStyles = ""
              }) => {
    const context = React.useContext(CommonContext) || {};
    const commonState = context.commonState || {};

    let resolvedColor = color;
    if (["", undefined].includes(resolvedColor)) {
        resolvedColor =
            commonState?.design?.colors?.theme?.primary ||
            "#2563eb";
    }

    return (
        <i
            onClick={click}
            style={{ color: `${resolvedColor}` }}
            className={`wlr wlrf-${icon} cursor-pointer ${fontSize} ${show} ${width}
   ${fontWeight} ${extraStyles}
   ${opactity}
    `}
        />
    );
};

export default Icon;