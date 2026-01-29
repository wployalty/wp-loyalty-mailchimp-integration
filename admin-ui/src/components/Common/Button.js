import React from 'react';

const Button = ({
                    bgColor = "bg-blue_primary",
                    textStyle = "text-white text-xs 2xl:text-sm_14_l_20 ",
                    padding = "p-3.5",
                    minwidth = "80px",
                    children,
                    icon = null,
                    others = "",
                    click = null,
                    outline = false,
                    outlineStyle = "border-primary",
                    disabled = false,
                    title = "",
                    shadow = false,
                    id = id,
                    extraStyle,
                }) => {
    const outlineStyles = outline ? `border ${outlineStyle}` : "";
    return (
        <button
            id={id}
            className={`antialiased font-medium no-underline  flex items-center justify-center space-x-2 outline-none tracking-normal whitespace-nowrap wp-loyalty-button ${shadow && "hover:shadow-lg "}
             ${bgColor} ${textStyle} ${padding} ${others}
              ${outlineStyles} cursor-pointer min-w-max rounded-md ${disabled ? `not-allowed` : "cursor-pointer"}`}
            onClick={disabled ? () => {
            } : click}
            title={title}
            style={{background: `${extraStyle} `}}
        >
            {icon}
            <span style={{
                marginTop: "2px"
            }} className="text-xs 2xl:text-sm font-semibold  ">{children}</span>
        </button>
    );
};

export default Button;

